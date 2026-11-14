package ui

import (
	"errors"
	"flag"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	tea "charm.land/bubbletea/v2"
	"github.com/charmbracelet/x/ansi"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/rift"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// update rewrites the golden screens instead of comparing against them: make test:go PKG=./internal/ui ARGS=-update.
var update = flag.Bool("update", false, "rewrite the golden screens")

// now is the time the fixture was taken at, which every test draws ages against.
var now = time.Unix(1_800_000_000, 0)

// fakeStream is a followed log that delivers given lines, then ends.
type fakeStream struct {
	lines  chan string
	closed bool
}

// Lines delivers the lines a test sends.
func (s *fakeStream) Lines() <-chan string { return s.lines }

// Close records that the dashboard stopped following.
func (s *fakeStream) Close() { s.closed = true }

// calls records what the dashboard asked of its backend.
type calls struct {
	refreshed int
	chains    [][]jobs.Task
	busy      bool
	stopped   bool
	events    chan jobs.Event
	plans     []string
	opened    []string
	copied    []string
	follows   []string
	commits   []string
	// stream is the latest followed log; each Follow hands out a new one, so streams can be told apart.
	stream     *fakeStream
	streams    []*fakeStream
	openErr    error
	followErr  error
	planErr    error
	planError  *string
	planExists bool
}

// fixture reads the contract fixture Rift's own tests produce exactly.
func fixture(t *testing.T) snapshot.Snapshot {
	t.Helper()

	data, err := os.ReadFile("../../testdata/snapshot.json")
	if err != nil {
		t.Fatal(err)
	}

	s, err := snapshot.Decode(data)
	if err != nil {
		t.Fatal(err)
	}

	return s
}

// backend creates a backend whose every part records its call, and whose tasks run `true`.
func backend(t *testing.T) (Backend, *calls) {
	t.Helper()

	c := &calls{stream: &fakeStream{lines: make(chan string, 8)}, events: make(chan jobs.Event, 1)}
	client, _ := rift.New(`["true"]`)

	return Backend{
		Next:    func() (snapshot.Snapshot, error) { return snapshot.Snapshot{}, errors.New("no more") },
		Refresh: func() { c.refreshed++ },
		Plan: func(project, branch string) (snapshot.Plan, error) {
			c.plans = append(c.plans, project+" "+branch)
			path := project + "-" + strings.ReplaceAll(branch, "/", "-")

			return snapshot.Plan{Branch: branch, Path: path, Domain: strings.ReplaceAll(branch, "/", "-") + ".myapp.test", ComposeName: "myapp-x", Exists: c.planExists, Error: c.planError}, c.planErr
		},
		Commits: func(dir string) []Commit {
			c.commits = append(c.commits, dir)

			return []Commit{{SHA: "abc1234", When: "3 minutes ago", Subject: "feat: rank results"}}
		},
		Follow: func(dir string, argv []string) (Stream, error) {
			c.follows = append(c.follows, dir+" "+strings.Join(argv, " "))
			c.stream = &fakeStream{lines: make(chan string, 8)}
			c.streams = append(c.streams, c.stream)

			return c.stream, c.followErr
		},
		Open: func(url string) error {
			c.opened = append(c.opened, url)

			return c.openErr
		},
		Copy: func(text string) tea.Cmd {
			c.copied = append(c.copied, text)

			return nil
		},
		Run:    func(chain ...jobs.Task) { c.chains = append(c.chains, chain) },
		Busy:   func() bool { return c.busy },
		Stop:   func() { c.stopped = true },
		Events: c.events,
		Rift:   client,
		Now:    func() time.Time { return now },
	}, c
}

// loaded creates a dashboard of the given size that has read the fixture, started in a directory.
func loaded(t *testing.T, here string, width, height int) (Model, *calls) {
	t.Helper()

	b, c := backend(t)
	m := New(b, here)
	next, _ := m.Update(tea.WindowSizeMsg{Width: width, Height: height})
	next, _ = next.Update(snapshotMsg{snap: fixture(t)})

	return next.(Model), c
}

// dashboard creates a 140 by 40 dashboard that has read the fixture, started in the search worktree.
func dashboard(t *testing.T) (Model, *calls) {
	t.Helper()

	return loaded(t, "/Sites/myapp-search", 140, 40)
}

// keyMsg builds the message a key sends, from the name the dashboard matches it by.
func keyMsg(name string) tea.KeyPressMsg {
	codes := map[string]rune{
		"enter": tea.KeyEnter, "esc": tea.KeyEscape, "tab": tea.KeyTab, "space": tea.KeySpace,
		"up": tea.KeyUp, "down": tea.KeyDown, "left": tea.KeyLeft, "right": tea.KeyRight,
		"pgup": tea.KeyPgUp, "pgdown": tea.KeyPgDown, "home": tea.KeyHome, "end": tea.KeyEnd, "backspace": tea.KeyBackspace,
	}

	switch {
	case name == "shift+tab":
		return tea.KeyPressMsg{Code: tea.KeyTab, Mod: tea.ModShift}
	case strings.HasPrefix(name, "ctrl+"):
		return tea.KeyPressMsg{Code: rune(name[len(name)-1]), Mod: tea.ModCtrl}
	case codes[name] != 0:
		return tea.KeyPressMsg{Code: codes[name]}
	}

	r := []rune(name)[0]

	return tea.KeyPressMsg{Code: r, Text: name}
}

// press sends keys one after another, returning the dashboard and the command the last one asked for.
func press(m Model, keys ...string) (Model, tea.Cmd) {
	var cmd tea.Cmd

	for _, k := range keys {
		var next tea.Model
		next, cmd = m.Update(keyMsg(k))
		m = next.(Model)
	}

	return m, cmd
}

// screen draws the dashboard as plain text, without colours.
func screen(m Model) string {
	return ansi.Strip(m.render())
}

// golden compares a screen with its golden file, rewriting the file with -update.
func golden(t *testing.T, name, got string) {
	t.Helper()

	path := filepath.Join("testdata", name+".golden")

	if *update {
		if err := os.WriteFile(path, []byte(got), 0o644); err != nil {
			t.Fatal(err)
		}

		return
	}

	want, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("no golden screen %s. Run `make test:go PKG=./internal/ui ARGS=-update` to write it", path)
	}

	if got != string(want) {
		t.Fatalf("screen %s changed:\n%s", name, got)
	}
}

// contains fails unless a screen holds every given text.
func contains(t *testing.T, got string, want ...string) {
	t.Helper()

	for _, w := range want {
		if !strings.Contains(got, w) {
			t.Errorf("screen lacks %q:\n%s", w, got)
		}
	}
}

// queued lists every task queued, chain after chain.
func (c *calls) queued() []jobs.Task {
	var tasks []jobs.Task

	for _, chain := range c.chains {
		tasks = append(tasks, chain...)
	}

	return tasks
}

// commandLines lists each task's directory and command line, joined by spaces.
func commandLines(tasks []jobs.Task) []string {
	lines := make([]string, 0, len(tasks))

	for _, task := range tasks {
		lines = append(lines, task.Dir+": "+strings.Join(task.Command, " "))
	}

	return lines
}

// mustPlan asks the dashboard's backend for a plan, failing the test on an error.
func mustPlan(t *testing.T, m Model, branch string) snapshot.Plan {
	t.Helper()

	plan, err := m.backend.Plan(m.modal.project.Root, branch)
	if err != nil {
		t.Fatal(err)
	}

	return plan
}

// taskNamed builds a task with only a label.
func taskNamed(label string) jobs.Task {
	return jobs.Task{Label: label}
}

// manyChanges gives the selected checkout more uncommitted files than its git tab can show.
func manyChanges(m Model) Model {
	search := &m.snap.Projects[0].Checkouts[1]
	search.Git.Changes = nil

	for i := range 60 {
		search.Git.Changes = append(search.Git.Changes, "file"+string(rune('A'+i%26))+string(rune('a'+i/26))+".php")
	}

	return m
}
