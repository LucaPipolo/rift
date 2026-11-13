// Package ui is the dashboard itself: what it shows, and what each key does.
package ui

import (
	"errors"
	"fmt"
	"os/exec"
	"strings"
	"time"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/rift"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// Commit is one commit the git tab lists.
type Commit struct {
	SHA     string
	When    string
	Subject string
}

// Stream is a running command whose output arrives a line at a time, such as followed logs.
type Stream interface {
	// Lines delivers each line, and closes once the command ends.
	Lines() <-chan string
	// Close stops the command.
	Close()
}

// Backend is everything the dashboard asks of the machine, so tests can stand in for each part.
type Backend struct {
	// Next waits for the next snapshot from `rift dash:watch`.
	Next func() (snapshot.Snapshot, error)
	// Refresh asks for a snapshot read afresh, after an action changed the machine.
	Refresh func()
	// Plan asks rift where a new worktree for a branch would go.
	Plan func(project, branch string) (snapshot.Plan, error)
	// Commits reads a checkout's latest commits.
	Commits func(dir string) []Commit
	// Follow starts following a command's output in a directory.
	Follow func(dir string, argv []string) (Stream, error)
	// Open opens a URL in the browser.
	Open func(url string) error
	// Copy puts text on the clipboard.
	Copy func(text string) tea.Cmd
	// Run queues a chain of tasks to run in the background, in order, stopping at the first failure.
	Run func(chain ...jobs.Task)
	// Busy reports whether a task is running or waiting to.
	Busy func() bool
	// Stop ends the running task and drops everything queued.
	Stop func()
	// Events delivers what happens to every task, in order.
	Events <-chan jobs.Event
	// Rift builds the commands that run rift itself.
	Rift rift.Client
	// Now tells the time.
	Now func() time.Time
}

// Model is the dashboard's state: what the machine looks like and what the person has opened.
type Model struct {
	backend Backend
	// here is the directory rift-dash started in, whose checkout it opens on.
	here string

	snap   snapshot.Snapshot
	loaded bool
	err    error
	width  int
	height int

	// The selection is kept as project and checkout roots, so it survives a new snapshot.
	focus     Panel
	project   string
	checkout  string
	tab       Tab
	routerTab RouterTab
	scroll    int
	marked    map[string]bool
	filter    string
	filtering bool
	help      bool
	modal     *Modal

	toast      string
	toastUntil time.Time

	log     []logLine
	running *jobs.Task
	frame   int

	// followFor and commitsFor name the checkout the logs and commits were asked for,
	// so a reply that arrives after the person moved on is dropped. followID numbers each
	// request to follow, so a stream that starts after a newer request is closed, not kept.
	follow     Stream
	followFor  string
	followID   int
	logs       []string
	paused     bool
	pausedAt   int
	commits    []Commit
	commitsFor string
}

// logLine is one line of the command log.
type logLine struct {
	kind jobs.Kind
	text string
}

// The messages the dashboard handles besides keys and the window size.
type (
	// snapshotMsg is the next snapshot, or why rift stopped sending them.
	snapshotMsg struct {
		snap snapshot.Snapshot
		err  error
	}
	// jobMsg is something that happened to a background task.
	jobMsg jobs.Event
	// tickMsg is the clock moving on.
	tickMsg time.Time
	// commitsMsg is a checkout's latest commits.
	commitsMsg struct {
		dir     string
		commits []Commit
	}
	// followMsg is a checkout's logs, now being followed, or why they cannot be.
	followMsg struct {
		id     int
		stream Stream
		err    error
	}
	// logLineMsg is the next line of a followed log, or its end.
	logLineMsg struct {
		stream Stream
		line   string
		done   bool
	}
	// planDueMsg says the person stopped typing a branch long enough to ask rift about it.
	planDueMsg struct {
		input string
	}
	// openedMsg is how opening a URL in the browser went.
	openedMsg struct {
		url string
		err error
	}
	// planMsg is where a worktree for the typed branch would go; submit says whether to create it.
	planMsg struct {
		input  string
		submit bool
		plan   snapshot.Plan
		err    error
	}
	// foregroundMsg is how a task that had the terminal ended.
	foregroundMsg struct {
		task jobs.Task
		err  error
	}
)

// How much of the command log the dashboard keeps and shows, and how much of a followed log it keeps.
const (
	logKeep  = 500
	logShown = 5
	logsKeep = 1000
)

// tickEvery is how often the clock moves on, fast enough for the spinner to turn smoothly.
const tickEvery = time.Second / 8

const toastFor = 4 * time.Second

// New creates the dashboard, which opens on the checkout of the directory it starts in, when there is one.
func New(backend Backend, here string) Model {
	return Model{
		backend: backend,
		here:    here,
		focus:   Checkouts,
		marked:  map[string]bool{},
	}
}

// Init starts waiting for snapshots and task events, and the clock.
func (m Model) Init() tea.Cmd {
	return tea.Batch(m.waitSnapshot(), m.waitJob(), m.tick())
}

// waitSnapshot waits for the next snapshot.
func (m Model) waitSnapshot() tea.Cmd {
	return func() tea.Msg {
		s, err := m.backend.Next()

		return snapshotMsg{snap: s, err: err}
	}
}

// waitJob waits for the next thing that happens to a task.
func (m Model) waitJob() tea.Cmd {
	return func() tea.Msg {
		return jobMsg(<-m.backend.Events)
	}
}

// tick moves the clock on, for ages, messages and the spinner.
func (m Model) tick() tea.Cmd {
	return tea.Tick(tickEvery, func(t time.Time) tea.Msg { return tickMsg(t) })
}

// Update applies one message to the dashboard, and returns what to wait for next.
func (m Model) Update(msg tea.Msg) (tea.Model, tea.Cmd) {
	switch msg := msg.(type) {
	case tea.WindowSizeMsg:
		m.width, m.height = msg.Width, msg.Height

		return m, nil
	case tea.KeyPressMsg:
		return m.key(msg.String())
	case snapshotMsg:
		return m.snapshot(msg)
	case jobMsg:
		return m.job(jobs.Event(msg))
	case tickMsg:
		m.frame++

		return m, m.tick()
	case commitsMsg:
		if msg.dir == m.commitsFor {
			m.commits = msg.commits
		}

		return m, nil
	case followMsg:
		return m.followed(msg)
	case logLineMsg:
		return m.logLine(msg)
	case planDueMsg:
		return m.planDue(msg)
	case planMsg:
		return m.planned(msg)
	case openedMsg:
		m.opened(msg)

		return m, nil
	case foregroundMsg:
		return m.foregroundDone(msg)
	}

	return m, nil
}

// snapshot takes in a new snapshot, then waits for the next.
func (m Model) snapshot(msg snapshotMsg) (tea.Model, tea.Cmd) {
	if msg.err != nil {
		m.err = msg.err
		m.log = append(m.log, logLine{kind: jobs.Failed, text: "rift stopped sending snapshots: " + msg.err.Error()})

		// Nothing else will come, so this is the one message that waits for no successor.
		return m, nil
	}

	if !m.loaded {
		m.project, m.checkout = locate(msg.snap, m.here)
	}

	m.snap, m.loaded, m.err = msg.snap, true, nil
	details := m.details()

	return m, tea.Batch(m.waitSnapshot(), details)
}

// locate finds the project and checkout a directory is in, the deepest checkout when worktrees nest.
func locate(s snapshot.Snapshot, dir string) (project, checkout string) {
	for _, p := range s.Projects {
		for _, c := range p.Checkouts {
			if (dir == c.Root || strings.HasPrefix(dir, c.Root+"/")) && len(c.Root) > len(checkout) {
				project, checkout = p.Root, c.Root
			}
		}
	}

	return project, checkout
}

// job adds what happened to a task to the command log, then waits for the next.
func (m Model) job(e jobs.Event) (tea.Model, tea.Cmd) {
	m.log = append(m.log, logLine{kind: e.Kind, text: e.Text})
	if len(m.log) > logKeep {
		m.log = m.log[len(m.log)-logKeep:]
	}

	switch e.Kind {
	case jobs.Started:
		task := e.Task
		m.running = &task
	case jobs.Done, jobs.Failed:
		m.running = nil
		// The task has just changed the machine, so show it now rather than in two seconds.
		m.backend.Refresh()
	}

	return m, m.waitJob()
}

// note shows a short message in the key bar for a few seconds.
func (m *Model) note(text string) {
	m.toast = text
	m.toastUntil = m.backend.Now().Add(toastFor)
}

// toastText returns the message the key bar shows, if it has not expired.
func (m Model) toastText() string {
	if m.backend.Now().Before(m.toastUntil) {
		return m.toast
	}

	return ""
}

// foreground hands the terminal to a task, such as a shell or a command that may ask for a password.
//
// With pause, it waits for enter afterwards, so the person can read what the task printed
// before the dashboard draws over it.
func (m Model) foreground(task jobs.Task, pause bool) tea.Cmd {
	argv := task.Command

	if pause {
		argv = append([]string{"sh", "-c", `printf '› %s\n\n' "$0"; "$@"; status=$?; printf '\nPress enter to return to rift dash.'; read -r _; exit $status`, task.Label}, argv...)
	}

	cmd := exec.Command(argv[0], argv[1:]...) //nolint:gosec // commands the dashboard builds itself.
	cmd.Dir = task.Dir

	return tea.ExecProcess(cmd, ended(task))
}

// ended reports how a foreground task ended, once it hands the terminal back.
func ended(task jobs.Task) tea.ExecCallback {
	return func(err error) tea.Msg { return foregroundMsg{task: task, err: err} }
}

// foregroundDone says how a foreground task ended, and reads the machine again.
func (m Model) foregroundDone(msg foregroundMsg) (tea.Model, tea.Cmd) {
	var exit *exec.ExitError

	switch {
	case errors.As(msg.err, &exit):
		m.note(fmt.Sprintf("✗ %s exited with code %d", msg.task.Label, exit.ExitCode()))
	case msg.err != nil:
		m.note("✗ " + msg.task.Label + ": " + msg.err.Error())
	default:
		m.note("✓ " + msg.task.Label)
	}

	m.backend.Refresh()

	return m, nil
}
