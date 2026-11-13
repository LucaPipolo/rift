package ui

import (
	"strings"
	"testing"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
)

// TestScreensMatchTheirGoldens draws every view the fixture can reach and compares it with its golden screen.
func TestScreensMatchTheirGoldens(t *testing.T) {
	cases := map[string]func(m Model) Model{
		"overview":         func(m Model) Model { return m },
		"overview-failed":  func(m Model) Model { m, _ = press(m, "j", "j"); return m },
		"overview-stopped": func(m Model) Model { m, _ = press(m, "G"); return m },
		"containers":       func(m Model) Model { m, _ = press(m, "j", "j", "]"); return m },
		"env":              func(m Model) Model { m, _ = press(m, "1", "j", "2", "]", "]", "]"); return m },
		"hooks":            func(m Model) Model { m, _ = press(m, "1", "j", "2", "[", "["); return m },
		"git": func(m Model) Model {
			m, cmd := press(m, "[")
			next, _ := m.Update(cmd())

			return next.(Model)
		},
		"routes":   func(m Model) Model { m, _ = press(m, "3"); return m },
		"config":   func(m Model) Model { m, _ = press(m, "3", "["); return m },
		"help":     func(m Model) Model { m, _ = press(m, "?"); return m },
		"down":     func(m Model) Model { m, _ = press(m, "d"); return m },
		"remove":   func(m Model) Model { m, _ = press(m, "x", "v"); return m },
		"palette":  func(m Model) Model { m, _ = press(m, ":", "r", "o", "u", "t"); return m },
		"filter":   func(m Model) Model { m, _ = press(m, "/", "o", "r"); return m },
		"projects": func(m Model) Model { m, _ = press(m, "1", "space"); return m },
		"marked":   func(m Model) Model { m, _ = press(m, "k", "space", "space"); return m },
	}

	for name, setUp := range cases {
		t.Run(name, func(t *testing.T) {
			m, _ := dashboard(t)

			golden(t, name, screen(setUp(m)))
		})
	}
}

// TestScreenOfANewWorktree compares the new worktree modal, with its plan, with its golden screen.
func TestScreenOfANewWorktree(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "n", "f", "e", "a", "t", "/", "x")
	next, _ := m.Update(planMsg{input: "feat/x", plan: mustPlan(t, m, "feat/x")})

	golden(t, "worktree", screen(next.(Model)))
}

// TestScreenOfARunningTask compares the command log of a running task with its golden screen.
func TestScreenOfARunningTask(t *testing.T) {
	m, _ := dashboard(t)

	for _, e := range []jobMsg{
		{Kind: 0, Text: "rift up · search.myapp.test", Task: taskNamed("rift up · search.myapp.test")},
		{Kind: 1, Text: "Starting containers"},
	} {
		next, _ := m.Update(e)
		m = next.(Model)
	}

	golden(t, "running", screen(m))
}

// TestScreenAtTheSmallestSize makes sure the layout holds at the smallest size it draws in.
func TestScreenAtTheSmallestSize(t *testing.T) {
	m, _ := loaded(t, "/Sites/myapp-search", MinWidth, MinHeight)

	golden(t, "smallest", screen(m))
}

// TestScreenOfATerminalTooSmallAsksForMore covers a terminal one column short.
func TestScreenOfATerminalTooSmallAsksForMore(t *testing.T) {
	m, _ := loaded(t, "", 79, 30)

	contains(t, screen(m), "rift dash needs at least 80×24; this terminal is 79×30.", "Make it larger, or press q to quit.")
}

// TestScreenIsEmptyBeforeTheSize covers the moment before Bubble Tea reports the terminal's size.
func TestScreenIsEmptyBeforeTheSize(t *testing.T) {
	b, _ := backend(t)

	if got := screen(New(b, "")); got != "" {
		t.Fatalf("got %q", got)
	}
}

// TestScreenSaysItIsReadingBeforeTheFirstSnapshot covers the moment before rift's first snapshot.
func TestScreenSaysItIsReadingBeforeTheFirstSnapshot(t *testing.T) {
	b, _ := backend(t)
	next, _ := New(b, "").Update(tea.WindowSizeMsg{Width: 100, Height: 30})

	contains(t, screen(next.(Model)), "Reading this machine…")
}

// TestViewUsesTheAlternateScreen makes sure quitting gives the person back the terminal as it was.
func TestViewUsesTheAlternateScreen(t *testing.T) {
	m, _ := dashboard(t)

	if !m.View().AltScreen {
		t.Fatal("not on the alternate screen")
	}
}

// TestARouterWhoseAdminAPIIsNotAnsweringSaysSo covers a router container that runs but cannot take routes.
func TestARouterWhoseAdminAPIIsNotAnsweringSaysSo(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Router.Answering = false

	contains(t, screen(m), "running, but its admin API is not answering")
}

// TestNoProjectsSaysHowToAddOne covers a machine where no project has been set up yet.
func TestNoProjectsSaysHowToAddOne(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Projects = nil
	m, _ = press(m, "1", "j")

	contains(t, screen(m), "No projects yet.", "Run `rift setup` in a project to list it here.")
}

// TestABrokenProjectShowsItsError covers a project whose rift.yaml cannot be read.
func TestABrokenProjectShowsItsError(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "1", "G", "2")

	contains(t, screen(m), "/Sites/broken/rift.yaml: `name` is required.", "Nothing selected.")
}

// TestAgesShowInMinutesAndDays covers the units the fixture leaves out.
func TestAgesShowInMinutesAndDays(t *testing.T) {
	m, _ := dashboard(t)
	minutes := now.Unix() - 300
	days := now.Unix() - 3*86400
	m.snap.Projects[0].Checkouts[0].Git.ChangedAt = &minutes
	m.snap.Projects[0].Checkouts[2].Git.ChangedAt = &days

	contains(t, screen(m), "5m", "3d")
}

// TestAMessageShowsInTheKeyBar covers a message while it lasts.
func TestAMessageShowsInTheKeyBar(t *testing.T) {
	m, _ := dashboard(t)
	m.note("Copied https://search.myapp.test")

	contains(t, screen(m), "Copied https://search.myapp.test")
}

// TestALongMessageTakesAtMostHalfTheBar makes sure the keys always show beside a message.
func TestALongMessageTakesAtMostHalfTheBar(t *testing.T) {
	m, _ := loaded(t, "/Sites/myapp-search", MinWidth, MinHeight)
	m.note("a message much longer than half of an eighty column terminal can hold, so it is cut")

	contains(t, screen(m), "a message much longer than half of an e…", " u up")
}

// TestTheCommandLogMarksAFinishedTask covers a task that succeeded.
func TestTheCommandLogMarksAFinishedTask(t *testing.T) {
	m, _ := dashboard(t)
	next, _ := m.Update(jobMsg(jobs.Event{Kind: jobs.Done, Text: "rift up · search.myapp.test · done in 3s"}))

	contains(t, screen(next.(Model)), "✓ rift up · search.myapp.test · done in 3s")
}

// TestALongViewSaysHowMuchIsHidden covers a view longer than the screen.
func TestALongViewSaysHowMuchIsHidden(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(manyChanges(m), "[")

	contains(t, screen(m), "more · ctrl+d", "fileAa.php")
}

// TestScrollingStopsAtTheEnd makes sure scrolling past the end still shows the last lines.
func TestScrollingStopsAtTheEnd(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(manyChanges(m), "[")

	for range 20 {
		m, _ = press(m, "ctrl+d")
	}

	contains(t, screen(m), "recent commits")

	if strings.Contains(screen(m), "more · ctrl+d") {
		t.Fatal("still hides lines at the end")
	}
}

// TestAnOverlayTallerThanTheScreen covers a modal on a screen too short for it.
func TestAnOverlayTallerThanTheScreen(t *testing.T) {
	if got := overlay("a\nb", "x\ny\nz", 1, 2); got != "x\ny" {
		t.Fatalf("got %q", got)
	}
}
