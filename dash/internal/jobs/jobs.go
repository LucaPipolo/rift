// Package jobs runs the dashboard's tasks one at a time in the background, reporting their output as it comes.
package jobs

import (
	"bufio"
	"fmt"
	"io"
	"os/exec"
	"regexp"
	"strings"
	"sync"
	"syscall"
	"time"
)

// Task is one command the dashboard runs for the person, in a given directory.
type Task struct {
	Label   string
	Dir     string
	Command []string
}

// Kind is what an event reports.
type Kind int

const (
	Started Kind = iota
	// Output reports one line a task printed.
	Output
	Done
	// Failed reports that a task failed, and how many tasks of its chain it skipped.
	Failed
)

// Event is something that happened to a task, in the order it happened.
type Event struct {
	Kind Kind
	Task Task
	Text string
}

// Queue runs chains of tasks in order: a task that fails drops the rest of its chain, but not the chains after it.
type Queue struct {
	events  chan Event
	mu      sync.Mutex
	chains  [][]Task
	running *exec.Cmd
	busy    bool
	now     func() time.Time
}

// eventBuffer is how many events wait for the dashboard before a task blocks on reporting the next.
const eventBuffer = 256

// A task's output is read a line at a time, with room for a long one.
const (
	lineBuffer = 64 * 1024
	lineMax    = 1 << 20
)

// noise matches colours, cursor movement and carriage returns, which mean nothing outside a terminal.
var noise = regexp.MustCompile(`\x1b\[[0-9;?]*[ -/]*[@-~]|\x1b\][^\x07]*\x07|\r`)

// spinner matches the one spinner frame a rift step draws when it has no terminal to animate in.
var spinner = regexp.MustCompile(`^\s*[\x{2800}-\x{28FF}]`)

// New creates an empty queue, timing tasks with the given clock.
func New(now func() time.Time) *Queue {
	return &Queue{events: make(chan Event, eventBuffer), now: now}
}

// Events delivers what happens to every task, in order.
func (q *Queue) Events() <-chan Event {
	return q.events
}

// Busy reports whether a task is running or waiting to.
func (q *Queue) Busy() bool {
	q.mu.Lock()
	defer q.mu.Unlock()

	return q.busy
}

// Add queues a chain of tasks after everything already queued.
func (q *Queue) Add(chain ...Task) {
	if len(chain) == 0 {
		return
	}

	q.mu.Lock()
	defer q.mu.Unlock()

	q.chains = append(q.chains, chain)

	if !q.busy {
		q.busy = true

		go q.work()
	}
}

// Stop ends the running task and drops everything queued.
func (q *Queue) Stop() {
	q.mu.Lock()
	defer q.mu.Unlock()

	q.chains = nil

	if q.running != nil {
		_ = syscall.Kill(-q.running.Process.Pid, syscall.SIGTERM)
	}
}

// work runs chains until none are left.
func (q *Queue) work() {
	for {
		q.mu.Lock()

		if len(q.chains) == 0 {
			q.busy = false
			q.mu.Unlock()

			return
		}

		chain := q.chains[0]
		q.chains = q.chains[1:]
		q.mu.Unlock()

		for i, task := range chain {
			if !q.run(task, len(chain)-i-1) {
				break
			}
		}
	}
}

// run runs one task, reporting its output and how it ended, and reports whether it succeeded.
func (q *Queue) run(task Task, after int) bool {
	started := q.now()
	q.events <- Event{Kind: Started, Task: task, Text: task.Label}

	cmd := exec.Command(task.Command[0], task.Command[1:]...) //nolint:gosec // commands the dashboard builds itself.
	cmd.Dir = task.Dir
	// Its own session, so Stop ends whatever it started too, such as docker compose under rift, and a task that
	// asks a question on the terminal fails instead of stopping the dashboard's terminal for good.
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}

	// One pipe for both streams, so their lines arrive in the order they were printed.
	out, in := io.Pipe()
	cmd.Stdout = in
	cmd.Stderr = in

	q.mu.Lock()
	err := cmd.Start()
	if err == nil {
		q.running = cmd
	}
	q.mu.Unlock()

	if err == nil {
		exited := make(chan error, 1)

		// Its output ends once it has exited, which is when reading stops. It stops being the running task as soon
		// as it is reaped, since its process group id may then belong to another program.
		go func() {
			err := cmd.Wait()

			q.mu.Lock()
			q.running = nil
			q.mu.Unlock()

			exited <- err
			_ = in.Close()
		}()

		q.read(task, out)
		err = <-exited
	} else {
		q.events <- Event{Kind: Output, Task: task, Text: err.Error()}
	}

	took := q.now().Sub(started).Round(time.Second)

	if err == nil {
		q.events <- Event{Kind: Done, Task: task, Text: fmt.Sprintf("%s · done in %s", task.Label, took)}

		return true
	}

	text := fmt.Sprintf("%s · failed after %s", task.Label, took)
	if after > 0 {
		text += fmt.Sprintf(" · skipped the %d step%s after it", after, plural(after))
	}

	q.events <- Event{Kind: Failed, Task: task, Text: text}

	return false
}

// read reports each line a task prints, without colours, blank lines or spinner frames.
func (q *Queue) read(task Task, out io.Reader) {
	lines := bufio.NewScanner(out)
	lines.Buffer(make([]byte, 0, lineBuffer), lineMax)

	for lines.Scan() {
		text := strings.TrimSpace(noise.ReplaceAllString(lines.Text(), ""))

		if text != "" && !spinner.MatchString(text) {
			q.events <- Event{Kind: Output, Task: task, Text: text}
		}
	}
}

// plural returns the plural ending for a count.
func plural(n int) string {
	if n == 1 {
		return ""
	}

	return "s"
}
