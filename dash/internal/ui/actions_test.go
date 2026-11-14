package ui

import (
	"errors"
	"reflect"
	"strings"
	"testing"
)

// untrusted returns the dashboard with a router whose CA is not trusted, or not even running.
func untrusted(m Model, running bool) Model {
	m.snap.Router.Trusted = false
	m.snap.Router.Running = running

	return m
}

// TestUpRunsInTheBackground covers u and s, which run the same way.
func TestUpRunsInTheBackground(t *testing.T) {
	for key, command := range map[string]string{"u": "up", "s": "setup"} {
		m, c := dashboard(t)
		press(m, key)

		got := commandLines(c.queued())
		if !reflect.DeepEqual(got, []string{"/Sites/myapp-search: true " + command + " --no-interaction --no-ansi"}) {
			t.Errorf("%s: got %v", key, got)
		}
	}
}

// TestUpRunsEachMarkedCheckout makes sure each marked checkout gets a chain of its own, so one failing never stops
// another.
func TestUpRunsEachMarkedCheckout(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "space", "space", "u")

	if len(c.chains) != 2 || len(m.marked) != 0 {
		t.Fatal("did not start both, or kept the marks")
	}
}

// TestUpRunsInTheForegroundWhileTheCAIsNotTrusted covers the first `rift up`, which may ask for a password to trust
// the CA.
func TestUpRunsInTheForegroundWhileTheCAIsNotTrusted(t *testing.T) {
	m, _ := dashboard(t)
	m = untrusted(m, true)

	if _, cmd := press(m, "u"); cmd == nil {
		t.Fatal("did not hand the terminal over")
	}

	m, _ = press(m, "space", "space", "u")
	contains(t, m.toastText(), "The CA is not trusted yet, so this starts one checkout, in the foreground.")
}

// TestUpWithNothingSelectedDoesNothing covers a filter that hides every checkout.
func TestUpWithNothingSelectedDoesNothing(t *testing.T) {
	m, c := dashboard(t)
	press(m, "/", "z", "enter", "u")

	if len(c.chains) != 0 {
		t.Fatal("started something")
	}
}

// TestUpAllStartsEveryStoppedCheckout makes sure checkouts already running are left alone.
func TestUpAllStartsEveryStoppedCheckout(t *testing.T) {
	m, c := dashboard(t)
	press(m, "U")

	got := commandLines(c.queued())
	if len(got) != 2 || !strings.HasPrefix(got[0], "/Sites/myapp-orders") || !strings.HasPrefix(got[1], "/Sites/myapp-fix-login") {
		t.Fatalf("got %v", got)
	}
}

// TestUpAllSaysWhenEverythingRuns covers U on a project with nothing left to start.
func TestUpAllSaysWhenEverythingRuns(t *testing.T) {
	m, _ := loaded(t, "/Sites/shop", 140, 40)
	m, _ = press(m, "U")

	contains(t, m.toastText(), "Every checkout is already running.")
}

// TestRestartStopsThenStarts makes sure the two run as one chain, so a failed stop skips the start.
func TestRestartStopsThenStarts(t *testing.T) {
	m, c := dashboard(t)
	press(m, "r")

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{
		"/Sites/myapp-search: true down --no-interaction --no-ansi",
		"/Sites/myapp-search: true up --no-interaction --no-ansi",
	}) {
		t.Fatalf("got %v", got)
	}
}

// TestRestartAsksForTheCAFirst covers restart, which runs in the background, where a password prompt cannot be
// answered.
func TestRestartAsksForTheCAFirst(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(untrusted(m, true), "r")

	contains(t, m.toastText(), "Trust the router's CA first: press T.")
}

// TestDownAsksThenStops guards against stopping a checkout on one stray key.
func TestDownAsksThenStops(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "d")

	if m.modal.title != "Stop search?" {
		t.Fatalf("got %s", m.modal.title)
	}

	m, _ = press(m, "y")

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{"/Sites/myapp-search: true down --no-interaction --no-ansi"}) {
		t.Fatalf("got %v", got)
	}
}

// TestDownAllListsWhatItStops makes sure the question names every checkout D would stop.
func TestDownAllListsWhatItStops(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "D")

	if m.modal.title != "Stop 4 checkouts?" || m.modal.lines[0] != "  myapp.test" {
		t.Fatalf("got %s %v", m.modal.title, m.modal.lines)
	}
}

// TestDownSaysWhenNothingRuns covers d on a checkout without containers, which has nothing to ask about.
func TestDownSaysWhenNothingRuns(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "G", "d")

	if m.modal != nil || m.toastText() != "Nothing to stop." {
		t.Fatal("asked to stop a stopped checkout")
	}
}

// TestDownAllWithoutAProject covers D before any project exists.
func TestDownAllWithoutAProject(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Projects = nil
	m, _ = press(m, "D")

	if m.modal != nil {
		t.Fatal("asked with no project")
	}
}

// TestANewWorktreeNeedsAProjectAndTheCA covers both reasons n refuses: an untrusted CA and a broken rift.yaml.
func TestANewWorktreeNeedsAProjectAndTheCA(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(untrusted(m, true), "n")
	contains(t, m.toastText(), "Trust the router's CA first: press T.")

	m, _ = loaded(t, "", 140, 40)
	m, _ = press(m, "1", "G", "n")
	contains(t, m.toastText(), "Fix this project's rift.yaml first.")
}

// TestRemovingAWorktreeAsksThenRemovesIt covers the fullest removal: running, with uncommitted files, and its volumes
// too.
func TestRemovingAWorktreeAsksThenRemovesIt(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "x", "v", "v", "v", "enter")

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{
		"/Sites/myapp-search: true down --no-interaction --no-ansi",
		"/Sites/myapp-search: docker compose -p myapp-search down --volumes",
		"/Sites/myapp: git worktree remove --force /Sites/myapp-search",
	}) {
		t.Fatalf("got %v", got)
	}

	if m.checkout != "" {
		t.Fatal("kept the removed worktree selected")
	}
}

// TestRemovingAStoppedCleanWorktreeOnlyRemovesIt makes sure nothing is stopped or forced that does not need to be.
func TestRemovingAStoppedCleanWorktreeOnlyRemovesIt(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "G", "x")
	contains(t, m.modal.lines[3], "The branch (detached) and its commits stay.")

	press(m, "y")

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{"/Sites/myapp: git worktree remove /Sites/myapp-fix-login"}) {
		t.Fatalf("got %v", got)
	}
}

// TestTheMainCheckoutCannotBeRemoved covers x on the main clone, which git cannot remove as a worktree.
func TestTheMainCheckoutCannotBeRemoved(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "g", "x")

	if m.modal != nil || m.toastText() != "The main checkout cannot be removed." {
		t.Fatal("offered to remove the main checkout")
	}
}

// TestOpensTheSelectedCheckout opens the checkout's first URL, outside the key press, since a browser can take its
// time to answer.
func TestOpensTheSelectedCheckout(t *testing.T) {
	m, c := dashboard(t)
	m, cmd := press(m, "o")

	if len(c.opened) != 0 {
		t.Fatal("opened the browser while handling the key")
	}

	next, _ := m.Update(cmd())
	m = next.(Model)

	if !reflect.DeepEqual(c.opened, []string{"https://search.myapp.test"}) || m.toastText() != "Opened https://search.myapp.test" {
		t.Fatalf("got %v %q", c.opened, m.toastText())
	}
}

// TestSaysWhyItCouldNotOpen covers a machine with no browser to open.
func TestSaysWhyItCouldNotOpen(t *testing.T) {
	m, c := dashboard(t)
	c.openErr = errors.New("no browser")
	m, cmd := press(m, "o")
	next, _ := m.Update(cmd())

	contains(t, next.(Model).toastText(), "Could not open https://search.myapp.test: no browser")
}

// TestOpensNothingThatIsNotRunning covers o on a stopped checkout, whose URL would not answer.
func TestOpensNothingThatIsNotRunning(t *testing.T) {
	m, c := dashboard(t)
	m, cmd := press(m, "G", "o")

	if cmd != nil || len(c.opened) != 0 || m.toastText() != "fix-login is not running, so there is nothing to open." {
		t.Fatalf("got %v %q", c.opened, m.toastText())
	}
}

// TestCopiesTheURL copies the checkout's first URL.
func TestCopiesTheURL(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "y")

	if !reflect.DeepEqual(c.copied, []string{"https://search.myapp.test"}) || m.toastText() != "Copied https://search.myapp.test" {
		t.Fatalf("got %v %q", c.copied, m.toastText())
	}
}

// TestActionsWithoutACheckoutDoNothing covers every action that needs a selected checkout, before any project exists.
func TestActionsWithoutACheckoutDoNothing(t *testing.T) {
	for _, key := range []string{"o", "y", "e", "E", "x"} {
		m, c := dashboard(t)
		m.snap.Projects = nil

		if _, cmd := press(m, key); cmd != nil || len(c.opened)+len(c.copied) != 0 {
			t.Errorf("%s acted", key)
		}
	}
}

// TestShellAndEditorTakeTheTerminal covers e and E, which need the terminal for themselves.
func TestShellAndEditorTakeTheTerminal(t *testing.T) {
	for _, key := range []string{"e", "E"} {
		m, _ := dashboard(t)

		if _, cmd := press(m, key); cmd == nil {
			t.Errorf("%s did not hand the terminal over", key)
		}
	}
}

// TestShellNeedsARunningContainer covers e on a stopped checkout, which has no container to open a shell in.
func TestShellNeedsARunningContainer(t *testing.T) {
	m, _ := dashboard(t)
	m, cmd := press(m, "G", "e")

	if cmd != nil || m.toastText() != "fix-login is not running, so there is no container to open a shell in." {
		t.Fatalf("got %q", m.toastText())
	}
}

// TestTrustTakesTheTerminal covers T, which asks for a password.
func TestTrustTakesTheTerminal(t *testing.T) {
	m, _ := dashboard(t)

	if _, cmd := press(m, "T"); cmd == nil {
		t.Fatal("did not hand the terminal over")
	}
}

// TestTrustNeedsTheRouter covers T before the router has issued a CA to trust.
func TestTrustNeedsTheRouter(t *testing.T) {
	m, _ := dashboard(t)
	m, cmd := press(untrusted(m, false), "T")

	if cmd != nil || m.toastText() != "Start a checkout first, so the router is running and has issued its CA." {
		t.Fatalf("got %q", m.toastText())
	}
}

// TestRestartRouterAsksFirst guards against interrupting every project's routes on one stray key.
func TestRestartRouterAsksFirst(t *testing.T) {
	m, c := dashboard(t)
	m, _ = press(m, "R")

	if m.modal.lines[1] != "Its 3 routes stop answering for a few seconds, then come back on their own." {
		t.Fatalf("got %v", m.modal.lines)
	}

	press(m, "enter")

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{"/Sites/myapp: true router:restart --no-interaction --no-ansi"}) {
		t.Fatalf("got %v", got)
	}
}

// TestRestartRouterSpeaksOfOneRoute covers the singular in the question.
func TestRestartRouterSpeaksOfOneRoute(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Router.Routes = m.snap.Router.Routes[:1]
	m, _ = press(m, "R")

	contains(t, m.modal.lines[1], "Its 1 route stops answering for a few seconds, then comes back on its own.")
}

// TestDoctorRunsAndShowsItsReport covers H before any project exists, when doctor runs from the current directory.
func TestDoctorRunsAndShowsItsReport(t *testing.T) {
	m, c := dashboard(t)
	m.snap.Projects = nil
	m, _ = press(m, "H")

	if m.focus != Router || m.routerTab != Doctor {
		t.Fatal("did not show the doctor tab")
	}

	got := commandLines(c.queued())
	if !reflect.DeepEqual(got, []string{".: true doctor --no-interaction --no-ansi"}) {
		t.Fatalf("got %v", got)
	}
}

// TestPluralLeavesOneSingular covers the singular, which no fixture count reaches.
func TestPluralLeavesOneSingular(t *testing.T) {
	if plural(1) != "" || plural(0) != "s" || plural(2) != "s" {
		t.Fatal("got the wrong ending")
	}
}
