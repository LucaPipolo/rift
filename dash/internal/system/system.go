// Package system is what the dashboard asks of the machine besides rift: logs, commits, the browser and the clipboard.
package system

import (
	"bufio"
	"fmt"
	"io"
	"os/exec"
	"strings"
	"sync"
	"syscall"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/ui"
)

const commitsShown = 10

// A followed log is read a line at a time, with room for a long one, and up to linesWaiting
// lines wait for the dashboard before reading pauses.
const (
	lineBuffer   = 64 * 1024
	lineMax      = 1 << 20
	linesWaiting = 64
)

// Commits reads a checkout's latest commits, or none when git cannot.
func Commits(dir string) []ui.Commit {
	cmd := exec.Command("git", "log", fmt.Sprintf("-%d", commitsShown), "--format=%h%x09%cr%x09%s")
	cmd.Dir = dir

	out, err := cmd.Output()
	if err != nil {
		return []ui.Commit{}
	}

	commits := []ui.Commit{}

	for line := range strings.SplitSeq(strings.TrimSpace(string(out)), "\n") {
		if parts := strings.SplitN(line, "\t", 3); len(parts) == 3 {
			commits = append(commits, ui.Commit{SHA: parts[0], When: parts[1], Subject: parts[2]})
		}
	}

	return commits
}

// kill signals a process group, and is replaced in tests that check who gets signalled.
var kill = syscall.Kill

// stream is a running command whose output arrives a line at a time.
type stream struct {
	cmd   *exec.Cmd
	lines chan string
	// done closes when the dashboard stops following, so the reader stops waiting for it.
	done chan struct{}
	once sync.Once
	// exited records that the command has been reaped, after which its pid may belong to another process.
	mu     sync.Mutex
	exited bool
}

// Follow starts a command in a directory and delivers its output, both streams, a line at a time.
func Follow(dir string, argv []string) (ui.Stream, error) {
	cmd := exec.Command(argv[0], argv[1:]...) //nolint:gosec // the logs command rift reports for a checkout.
	cmd.Dir = dir
	// Its own session, so Close stops whatever it started too, and it can never stop on reading the terminal.
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}

	out, in := io.Pipe()
	cmd.Stdout = in
	cmd.Stderr = in

	if err := cmd.Start(); err != nil {
		return nil, err
	}

	s := &stream{cmd: cmd, lines: make(chan string, linesWaiting), done: make(chan struct{})}

	go func() {
		_ = cmd.Wait()

		s.mu.Lock()
		s.exited = true
		s.mu.Unlock()

		_ = in.Close()
	}()

	go s.read(out)

	return s, nil
}

// read delivers each line until the output ends or the dashboard stops following.
//
// Once it stops, the output is closed, so the command's writes fail rather than wait for a reader that is gone.
func (s *stream) read(out *io.PipeReader) {
	defer close(s.lines)
	defer func() { _ = out.Close() }()

	scanner := bufio.NewScanner(out)
	scanner.Buffer(make([]byte, 0, lineBuffer), lineMax)

	for scanner.Scan() {
		select {
		case s.lines <- scanner.Text():
		case <-s.done:
			return
		}
	}
}

// Lines delivers each line, and closes once the command ends.
func (s *stream) Lines() <-chan string {
	return s.lines
}

// Close stops the command, unless it has already exited.
func (s *stream) Close() {
	s.once.Do(func() {
		close(s.done)

		s.mu.Lock()
		defer s.mu.Unlock()

		if !s.exited {
			_ = kill(-s.cmd.Process.Pid, syscall.SIGTERM)
		}
	})
}

// Opener returns how to open a URL in the browser on an operating system.
func Opener(goos string, run func(name string, args ...string) error) func(url string) error {
	name := "xdg-open"
	if goos == "darwin" {
		name = "open"
	}

	return func(url string) error {
		return run(name, url)
	}
}

// Copier returns how to put text on the clipboard on an operating system.
//
// We decided to use pbcopy on macOS because its Terminal ignores the escape
// sequence that asks a terminal to set the clipboard, which every other
// terminal, over SSH too, understands.
func Copier(goos string, pipe func(text, name string) error) func(text string) tea.Cmd {
	if goos != "darwin" {
		return tea.SetClipboard
	}

	return func(text string) tea.Cmd {
		_ = pipe(text, "pbcopy")

		return nil
	}
}

// Run runs a command, waiting for it.
func Run(name string, args ...string) error {
	return exec.Command(name, args...).Run() //nolint:gosec // open or xdg-open with a URL.
}

// Pipe runs a command with text on its standard input.
func Pipe(text, name string) error {
	cmd := exec.Command(name) //nolint:gosec // pbcopy.
	cmd.Stdin = strings.NewReader(text)

	return cmd.Run()
}
