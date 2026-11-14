package ui

import (
	"errors"
	"reflect"
	"testing"

	tea "charm.land/bubbletea/v2"
)

// TestAModalClosesWithoutActing covers every key that answers no, and makes sure no key reaches the panels beneath.
func TestAModalClosesWithoutActing(t *testing.T) {
	for _, key := range []string{"esc", "n", "q"} {
		m, c := dashboard(t)
		m, _ = press(m, "d", "j", key)

		if m.modal != nil || len(c.chains) != 0 || m.checkout != "/Sites/myapp-search" {
			t.Errorf("%s acted or moved", key)
		}
	}
}

// TestThePaletteFindsAndRunsACommand covers typing and editing a search, then running the one match.
func TestThePaletteFindsAndRunsACommand(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, ":", "d", "o", "c", "x", "backspace", "space", "backspace")

	if found := matching(m.modal.input); len(found) != 1 || found[0].key != "H" {
		t.Fatalf("got %v", found)
	}

	m, _ = press(m, "enter")
	if m.modal != nil || m.routerTab != Doctor {
		t.Fatal("did not run doctor")
	}
}

// TestThePaletteMovesThroughItsMatches makes sure the highlight stops at either end.
func TestThePaletteMovesThroughItsMatches(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, ":", "down", "tab", "up", "shift+tab", "shift+tab")

	if m.modal.selected != 0 {
		t.Fatalf("got %d", m.modal.selected)
	}

	for range len(commands) + 3 {
		m, _ = press(m, "down")
	}

	if m.modal.selected != len(commands)-1 {
		t.Fatalf("got %d", m.modal.selected)
	}

	if m, _ = press(m, "pgdown"); m.modal.selected != len(commands)-1 {
		t.Fatal("moved on an unknown key")
	}
}

// TestThePaletteWithNoMatchDoesNothing covers enter with nothing to run.
func TestThePaletteWithNoMatchDoesNothing(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, ":", "z", "z", "z", "enter")

	if m.modal == nil {
		t.Fatal("closed with nothing to run")
	}

	contains(t, screen(m), "No command matches.")
}

// TestThePaletteWithNoMatchIgnoresMovingDown covers down and tab with nothing listed, which once selected a
// command before the first and crashed the dashboard on enter.
func TestThePaletteWithNoMatchIgnoresMovingDown(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, ":", "z", "z", "z", "down", "tab", "enter")

	if m.modal == nil {
		t.Fatal("closed with nothing to run")
	}
}

// TestANewWorktreeIsPlannedAsTheBranchIsTyped covers editing the branch, keys that type nothing, and an emptied
// branch, which is not planned.
func TestANewWorktreeIsPlannedAsTheBranchIsTyped(t *testing.T) {
	m, c := dashboard(t)
	m, cmd := press(m, "n", "f", "space", "u")

	if m.modal.input != "fu" || cmd == nil {
		t.Fatalf("got %q", m.modal.input)
	}

	m, cmd = press(m, "backspace", "up")
	if m.modal.input != "f" || cmd != nil {
		t.Fatalf("got %q", m.modal.input)
	}

	m, _ = press(m, "backspace")
	if _, cmd = press(m, "backspace"); cmd != nil {
		t.Fatal("planned an empty branch")
	}

	m, cmd = press(m, "x")
	next, cmd := m.Update(cmd())
	next, _ = next.Update(cmd())

	if got := next.(Model).modal.plan; got == nil || got.Path != "/Sites/myapp-x" || c.plans[0] != "/Sites/myapp x" {
		t.Fatalf("got %+v %v", got, c.plans)
	}
}

// TestANewWorktreeIsPlannedOnceTypingPauses makes sure a branch typed quickly asks rift once, for what was typed
// last, rather than once per letter.
func TestANewWorktreeIsPlannedOnceTypingPauses(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "n")

	var ticks []tea.Cmd

	for _, k := range []string{"a", "b", "c"} {
		var cmd tea.Cmd
		m, cmd = press(m, k)
		ticks = append(ticks, cmd)
	}

	for _, tick := range ticks {
		next, ask := m.Update(tick())
		if ask != nil {
			next, _ = next.Update(ask())
		}

		m = next.(Model)
	}

	if !reflect.DeepEqual(c.plans, []string{"/Sites/myapp abc"}) || m.modal.plan == nil {
		t.Fatalf("got %v", c.plans)
	}
}

// TestAPlanDueAfterTheModalClosedAsksNothing covers the pause ending after the modal closed or became another one.
func TestAPlanDueAfterTheModalClosedAsksNothing(t *testing.T) {
	m, c := dashboard(t)

	if _, cmd := m.Update(planDueMsg{input: "a"}); cmd != nil {
		t.Fatal("asked with no modal open")
	}

	m, _ = press(m, ":")
	if _, cmd := m.Update(planDueMsg{input: ""}); cmd != nil || len(c.plans) != 0 {
		t.Fatal("asked from the command palette")
	}
}

// TestAnExistingBranchIsCheckedOutIntoTheWorktree makes sure a branch that exists is not created again.
func TestAnExistingBranchIsCheckedOutIntoTheWorktree(t *testing.T) {
	m, c := dashboard(t)
	c.planExists = true
	m, _ = press(m, "n", "m", "a", "i", "n")
	_, cmd := press(m, "enter")
	_, _ = m.Update(cmd())

	if got := commandLines(c.queued()); len(got) != 2 || got[0] != "/Sites/myapp: git worktree add /Sites/myapp-main main" {
		t.Fatalf("got %v", got)
	}
}

// TestANewWorktreeIsCreatedThenSetUp makes sure the worktree is created before `rift setup` runs in it, as one chain.
func TestANewWorktreeIsCreatedThenSetUp(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "n", "f", "e", "a", "t", "/", "x")
	_, cmd := press(m, "enter")
	next, _ := m.Update(cmd())
	m = next.(Model)

	if m.modal != nil || m.checkout != "/Sites/myapp-feat-x" {
		t.Fatalf("got %v %s", m.modal, m.checkout)
	}

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{
		"/Sites/myapp: git worktree add -b feat/x /Sites/myapp-feat-x",
		"/Sites/myapp-feat-x: true setup --no-interaction --no-ansi",
	}) {
		t.Fatalf("got %v", got)
	}
}

// TestANewWorktreeRefusedByRiftShowsWhy covers a branch rift will not make a worktree for.
func TestANewWorktreeRefusedByRiftShowsWhy(t *testing.T) {
	m, c := dashboard(t)
	reason := "That is not a valid branch name."
	c.planError = &reason
	m, _ = press(m, "n", "a")
	_, cmd := press(m, "enter")
	next, _ := m.Update(cmd())

	contains(t, screen(next.(Model)), "That is not a valid branch name.")
}

// TestANewWorktreeRiftCouldNotPlanShowsWhy covers rift failing to answer at all.
func TestANewWorktreeRiftCouldNotPlanShowsWhy(t *testing.T) {
	m, c := dashboard(t)
	c.planErr = errors.New("rift could not plan the worktree")
	m, _ = press(m, "n", "a")
	_, cmd := press(m, "enter")
	next, _ := m.Update(cmd())

	contains(t, screen(next.(Model)), "rift could not plan the worktree")
}

// TestAPlanForAnOlderBranchIsIgnored covers a slow plan for a branch since typed over, and a plan arriving after the
// modal closed.
func TestAPlanForAnOlderBranchIsIgnored(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "n", "a", "b")
	next, _ := m.Update(planMsg{input: "a", submit: true})

	if next.(Model).modal == nil || next.(Model).modal.plan != nil {
		t.Fatal("took an outdated plan")
	}

	m.modal = nil
	if next, _ = m.Update(planMsg{input: "a"}); next.(Model).modal != nil {
		t.Fatal("reopened a closed modal")
	}
}
