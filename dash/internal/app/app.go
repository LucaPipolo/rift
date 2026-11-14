// Package app starts rift-dash with what the process was given.
package app

import (
	"fmt"
	"io"
	"runtime"
	"time"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/rift"
	"github.com/LucaPipolo/rift/dash/internal/system"
	"github.com/LucaPipolo/rift/dash/internal/ui"
)

// Main runs rift-dash until the person quits, and returns its exit code.
func Main(getenv func(string) string, dir string, stdin io.Reader, stdout, stderr io.Writer, options ...tea.ProgramOption) int {
	client, err := rift.New(getenv("RIFT_COMMAND"))
	if err != nil {
		return fail(stderr, err)
	}

	watch, err := client.Watch(dir)
	if err != nil {
		return fail(stderr, err)
	}

	defer watch.Close()

	queue := jobs.New(time.Now)
	defer queue.Stop()

	backend := ui.Backend{
		Next:    watch.Next,
		Refresh: watch.Refresh,
		Plan:    client.Plan,
		Commits: system.Commits,
		Follow:  system.Follow,
		Open:    system.Opener(runtime.GOOS, system.Run),
		Copy:    system.Copier(runtime.GOOS, system.Pipe),
		Run:     queue.Add,
		Busy:    queue.Busy,
		Stop:    queue.Stop,
		Events:  queue.Events(),
		Rift:    client,
		Now:     time.Now,
	}

	options = append([]tea.ProgramOption{tea.WithInput(stdin), tea.WithOutput(stdout)}, options...)

	if _, err := tea.NewProgram(ui.New(backend, dir), options...).Run(); err != nil {
		return fail(stderr, fmt.Errorf("rift-dash stopped: %w", err))
	}

	return 0
}

// fail prints an error as the sentence a person reads, and returns the exit code for a failure.
//
// We decided to add the full stop here rather than in each error because Go error values are
// wrapped into one another, where a full stop would land mid-sentence.
func fail(stderr io.Writer, err error) int {
	_, _ = fmt.Fprintln(stderr, err.Error()+".")

	return 1
}
