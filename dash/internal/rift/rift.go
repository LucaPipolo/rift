// Package rift runs the rift binary that started rift-dash: for snapshots, worktree plans and actions.
package rift

import (
	"bufio"
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"os/exec"
	"strings"
	"sync"

	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// A snapshot of many projects is one long line, so lines are read with room to grow.
const (
	snapshotBuffer = 1 << 20
	snapshotMax    = 64 << 20
)

// Client runs rift as `rift dash` started it.
type Client struct {
	argv []string
}

// New reads the command `rift dash` passes in RIFT_COMMAND, as a JSON list.
func New(command string) (Client, error) {
	var argv []string

	if err := json.Unmarshal([]byte(command), &argv); err != nil || len(argv) == 0 || argv[0] == "" {
		return Client{}, errors.New("rift-dash cannot run on its own. Run `rift dash` instead")
	}

	return Client{argv: argv}, nil
}

// Command builds the command line that runs a rift command in the background, without prompts or colours.
func (c Client) Command(args ...string) []string {
	return append(c.Interactive(args...), "--no-interaction", "--no-ansi")
}

// Interactive builds the command line that runs a rift command in the foreground, where it may ask questions.
func (c Client) Interactive(args ...string) []string {
	return append(append([]string{}, c.argv...), args...)
}

// Plan asks rift where a new worktree for a branch would go.
func (c Client) Plan(project, branch string) (snapshot.Plan, error) {
	argv := c.Command("dash:plan", project, branch)

	out, err := exec.Command(argv[0], argv[1:]...).Output() //nolint:gosec // rift itself, with arguments it parses.
	if err != nil {
		return snapshot.Plan{}, fmt.Errorf("rift could not plan the worktree: %w", err)
	}

	return snapshot.DecodePlan(out)
}

// Watch is a running `rift dash:watch`, printing a snapshot per line until it is closed.
type Watch struct {
	stdin  io.WriteCloser
	lines  *bufio.Scanner
	stderr *bytes.Buffer
	// mu keeps Refresh and Close from writing to stdin at the same time.
	mu sync.Mutex
}

// Watch starts `rift dash:watch` in a directory, whose project the dashboard opens on.
func (c Client) Watch(dir string) (*Watch, error) {
	argv := c.Command("dash:watch")
	cmd := exec.Command(argv[0], argv[1:]...) //nolint:gosec // rift itself, with arguments it parses.
	cmd.Dir = dir

	stdoutR, stdoutW := io.Pipe()
	var stderr bytes.Buffer

	cmd.Stdout = stdoutW
	cmd.Stderr = &stderr

	// A real pipe, so Wait never waits on a goroutine copying input nobody closes. Making one
	// fails only when the process is out of file descriptors, which Start would fail on too.
	stdin, pipeErr := cmd.StdinPipe()
	if err := errors.Join(pipeErr, cmd.Start()); err != nil {
		return nil, fmt.Errorf("rift-dash could not start rift: %w", err)
	}

	// Its output ends only once it has exited, so stderr holds why by the time Next sees the end.
	go func() {
		_ = cmd.Wait()
		_ = stdoutW.Close()
	}()

	lines := bufio.NewScanner(stdoutR)
	lines.Buffer(make([]byte, 0, snapshotBuffer), snapshotMax)

	return &Watch{stdin: stdin, lines: lines, stderr: &stderr}, nil
}

// Next waits for the next snapshot.
func (w *Watch) Next() (snapshot.Snapshot, error) {
	if w.lines.Scan() {
		return snapshot.Decode(w.lines.Bytes())
	}

	if reason := strings.TrimSpace(w.stderr.String()); reason != "" {
		return snapshot.Snapshot{}, errors.New(reason)
	}

	return snapshot.Snapshot{}, errors.New("rift exited without saying why")
}

// Refresh asks for a snapshot now, read afresh, as after an action that changed the machine.
func (w *Watch) Refresh() {
	w.mu.Lock()
	defer w.mu.Unlock()

	_, _ = io.WriteString(w.stdin, "refresh\n")
}

// Close stops `rift dash:watch` by closing its input, which it reads as the end.
func (w *Watch) Close() {
	w.mu.Lock()
	defer w.mu.Unlock()

	_ = w.stdin.Close()
}
