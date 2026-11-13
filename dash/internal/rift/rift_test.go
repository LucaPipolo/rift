package rift

import (
	"reflect"
	"strings"
	"testing"
)

// fake returns a client running the fake rift script, misbehaving as FAKE_RIFT says.
func fake(t *testing.T, mode string) Client {
	t.Helper()
	t.Setenv("FAKE_RIFT", mode)

	c, err := New(`["sh", "testdata/fake-rift"]`)
	if err != nil {
		t.Fatal(err)
	}

	return c
}

// TestNewRefusesWhatRiftDashDidNotGet covers every RIFT_COMMAND that is not a non-empty JSON list.
func TestNewRefusesWhatRiftDashDidNotGet(t *testing.T) {
	for _, command := range []string{"", "nope", "[]", `[""]`} {
		if _, err := New(command); err == nil || err.Error() != "rift-dash cannot run on its own. Run `rift dash` instead" {
			t.Errorf("%q: got %v", command, err)
		}
	}
}

// TestCommandTurnsPromptsAndColoursOff covers background commands, which have no terminal to prompt or colour.
func TestCommandTurnsPromptsAndColoursOff(t *testing.T) {
	c, _ := New(`["/opt/homebrew/bin/rift"]`)

	if got := c.Command("up"); !reflect.DeepEqual(got, []string{"/opt/homebrew/bin/rift", "up", "--no-interaction", "--no-ansi"}) {
		t.Fatalf("got %v", got)
	}
}

// TestInteractiveLeavesPromptsOn covers foreground commands, which may need to ask for a password.
func TestInteractiveLeavesPromptsOn(t *testing.T) {
	c, _ := New(`["php", "/app/rift"]`)

	if got := c.Interactive("trust"); !reflect.DeepEqual(got, []string{"php", "/app/rift", "trust"}) {
		t.Fatalf("got %v", got)
	}
}

// TestPlanReadsWhatRiftPlans reads a plan from the fake rift.
func TestPlanReadsWhatRiftPlans(t *testing.T) {
	p, err := fake(t, "").Plan("/Sites/myapp", "feat/x")

	if err != nil || p.Branch != "feat/x" || p.Path != "/Sites/myapp-x" || p.Domain != "x.myapp.test" {
		t.Fatalf("got %+v, %v", p, err)
	}
}

// TestPlanReportsAFailure covers rift exiting with an error instead of printing a plan.
func TestPlanReportsAFailure(t *testing.T) {
	if _, err := fake(t, "fail").Plan("/Sites/myapp", "x"); err == nil || !strings.HasPrefix(err.Error(), "rift could not plan the worktree") {
		t.Fatalf("got %v", err)
	}
}

// TestWatchReadsASnapshotPerLine covers the whole life of a watch: a first snapshot, one on refresh, and the end once
// it is closed.
func TestWatchReadsASnapshotPerLine(t *testing.T) {
	w, err := fake(t, "").Watch(".")
	if err != nil {
		t.Fatal(err)
	}

	first, err := w.Next()
	if err != nil || first.Projects[0].Name != "myapp" {
		t.Fatalf("got %+v, %v", first.Projects, err)
	}

	w.Refresh()

	if second, err := w.Next(); err != nil || len(second.Projects) != 3 {
		t.Fatalf("got %+v, %v", second.Projects, err)
	}

	w.Close()

	if _, err := w.Next(); err == nil || err.Error() != "rift exited without saying why" {
		t.Fatalf("got %v", err)
	}
}

// TestWatchReportsWhyRiftStopped makes sure the reason rift printed on stderr reaches the person, not a generic
// error.
func TestWatchReportsWhyRiftStopped(t *testing.T) {
	w, err := fake(t, "fail").Watch(".")
	if err != nil {
		t.Fatal(err)
	}

	if _, err := w.Next(); err == nil || err.Error() != "rift: projects.yaml: `projects` must be a list of directories." {
		t.Fatalf("got %v", err)
	}
}

// TestWatchReportsASnapshotItCannotRead covers rift printing a line that is not a snapshot.
func TestWatchReportsASnapshotItCannotRead(t *testing.T) {
	w, err := fake(t, "garbage").Watch(".")
	if err != nil {
		t.Fatal(err)
	}

	if _, err := w.Next(); err == nil || !strings.HasPrefix(err.Error(), "rift sent a snapshot rift-dash cannot read") {
		t.Fatalf("got %v", err)
	}
}

// TestWatchReportsARiftThatCannotStart covers a rift binary that is no longer where `rift dash` said it was.
func TestWatchReportsARiftThatCannotStart(t *testing.T) {
	c, _ := New(`["/nonexistent/rift"]`)

	if _, err := c.Watch("."); err == nil || !strings.HasPrefix(err.Error(), "rift-dash could not start rift") {
		t.Fatalf("got %v", err)
	}
}
