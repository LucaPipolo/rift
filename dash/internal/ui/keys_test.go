package ui

import (
	"reflect"
	"strings"
	"testing"

	tea "charm.land/bubbletea/v2"
)

// TestMovesAround covers every key that moves the selection in the checkouts panel.
func TestMovesAround(t *testing.T) {
	cases := []struct {
		keys     []string
		checkout string
	}{
		{[]string{"j"}, "/Sites/myapp-pdf-export"},
		{[]string{"down"}, "/Sites/myapp-pdf-export"},
		{[]string{"k"}, "/Sites/myapp"},
		{[]string{"up"}, "/Sites/myapp"},
		{[]string{"pgdown"}, "/Sites/myapp-fix-login"},
		{[]string{"pgup"}, "/Sites/myapp"},
		{[]string{"G"}, "/Sites/myapp-fix-login"},
		{[]string{"end"}, "/Sites/myapp-fix-login"},
		{[]string{"g"}, "/Sites/myapp"},
		{[]string{"home"}, "/Sites/myapp"},
	}

	for _, c := range cases {
		m, _ := dashboard(t)
		m, _ = press(m, c.keys...)

		if m.checkout != c.checkout {
			t.Errorf("%v: got %s", c.keys, m.checkout)
		}
	}
}

// TestCyclesTheFocus covers tab and shift+tab wrapping around the panels.
func TestCyclesTheFocus(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "tab", "tab", "shift+tab")

	if m.focus != Router {
		t.Fatalf("got %v", m.focus)
	}
}

// TestChangesTabsInTheViewTheFocusIsOn makes sure the router's tabs and a checkout's tabs change apart.
func TestChangesTabsInTheViewTheFocusIsOn(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "l", "right", "h")

	if m.tab != Containers {
		t.Fatalf("got %v", m.tab)
	}

	m, _ = press(m, "3", "left")
	if m.routerTab != Config || m.tab != Containers {
		t.Fatalf("got %v %v", m.routerTab, m.tab)
	}
}

// TestOpensAndClosesTheHelp makes sure the help holds every other key while it is open.
func TestOpensAndClosesTheHelp(t *testing.T) {
	for _, closing := range []string{"?", "esc", "q", "enter"} {
		m, _ := dashboard(t)
		m, _ = press(m, "?", "j")

		if !m.help || m.checkout != "/Sites/myapp-search" {
			t.Fatalf("help did not hold the keys")
		}

		if m, _ = press(m, closing); m.help {
			t.Errorf("%s did not close the help", closing)
		}
	}
}

// TestQuitsAtOnceWhenNothingRuns covers q and ctrl+c.
func TestQuitsAtOnceWhenNothingRuns(t *testing.T) {
	for _, key := range []string{"q", "ctrl+c"} {
		m, _ := dashboard(t)

		if _, cmd := press(m, key); cmd == nil || !reflect.DeepEqual(cmd(), tea.QuitMsg{}) {
			t.Errorf("%s did not quit", key)
		}
	}
}

// TestAsksBeforeQuittingWhileATaskRuns guards against quitting part-way through a task on one stray key.
func TestAsksBeforeQuittingWhileATaskRuns(t *testing.T) {
	m, c := dashboard(t)
	c.busy = true

	m, cmd := press(m, "q")
	if cmd != nil || m.modal == nil || m.modal.title != "Quit while a command is running?" {
		t.Fatal("did not ask")
	}

	if _, cmd = press(m, "enter"); cmd == nil || !reflect.DeepEqual(cmd(), tea.QuitMsg{}) || !c.stopped {
		t.Fatal("did not stop and quit once confirmed")
	}
}

// TestFiltersTheCheckouts covers typing, editing and keeping a filter, then clearing it.
func TestFiltersTheCheckouts(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "/", "o", "r", "x", "backspace", "space", "backspace", "up", "enter")

	if m.filtering || m.filter != "or" {
		t.Fatalf("got %v %q", m.filtering, m.filter)
	}

	labels := []string{}
	for _, c := range m.visible() {
		labels = append(labels, c.Label)
	}

	if !reflect.DeepEqual(labels, []string{"pdf-export", "orders"}) {
		t.Fatalf("got %v", labels)
	}

	if m, _ = press(m, "esc"); m.filter != "" {
		t.Fatal("esc kept the filter")
	}
}

// TestMarksOnlyInTheCheckoutsPanel makes sure space does nothing where there is no checkout to mark.
func TestMarksOnlyInTheCheckoutsPanel(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "3", "space")

	if len(m.marked) != 0 {
		t.Fatal("marked from the router panel")
	}
}

// TestUnknownKeysDoNothing covers a key no binding uses.
func TestUnknownKeysDoNothing(t *testing.T) {
	m, _ := dashboard(t)

	if next, cmd := press(m, "z"); cmd != nil || next.modal != nil {
		t.Fatal("acted on an unknown key")
	}
}

// TestScrollingMovesThroughALongViewAndBack covers ctrl+d and ctrl+u.
func TestScrollingMovesThroughALongViewAndBack(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(manyChanges(m), "[", "ctrl+d", "ctrl+d")

	if strings.Contains(screen(m), "fileAa.php") || !strings.Contains(screen(m), "fileAb.php") {
		t.Fatalf("did not scroll:\n%s", screen(m))
	}

	m, _ = press(m, "ctrl+u", "ctrl+u", "ctrl+u")
	contains(t, screen(m), "fileAa.php")
}

// TestMovingAwayScrollsBackToTheTop makes sure a view opens at its top, not where another one was scrolled to.
func TestMovingAwayScrollsBackToTheTop(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(manyChanges(m), "[", "ctrl+d", "j", "k")

	if m.scroll != 0 {
		t.Fatalf("kept scroll %d", m.scroll)
	}
}
