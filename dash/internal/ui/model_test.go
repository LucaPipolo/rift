package ui

import (
	"errors"
	"os/exec"
	"strings"
	"testing"
	"time"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// TestInitWaitsForSnapshotsTasksAndTheClock covers what the dashboard starts listening to.
func TestInitWaitsForSnapshotsTasksAndTheClock(t *testing.T) {
	m, _ := dashboard(t)

	if m.Init() == nil {
		t.Fatal("Init asked for nothing")
	}
}

// TestOpensOnTheCheckoutItStartedIn covers `rift dash` run from a worktree.
func TestOpensOnTheCheckoutItStartedIn(t *testing.T) {
	m, _ := dashboard(t)

	if m.project != "/Sites/myapp" || m.checkout != "/Sites/myapp-search" {
		t.Fatalf("got %s %s", m.project, m.checkout)
	}
}

// TestOpensOnTheCheckoutOfADirectoryInsideIt covers `rift dash` run from deep inside a checkout.
func TestOpensOnTheCheckoutOfADirectoryInsideIt(t *testing.T) {
	m, _ := loaded(t, "/Sites/shop/app/Http", 140, 40)

	if m.checkout != "/Sites/shop" {
		t.Fatalf("got %s", m.checkout)
	}
}

// TestKeepsItsSelectionAcrossSnapshots makes sure a new snapshot never moves the selection back to where rift-dash
// started.
func TestKeepsItsSelectionAcrossSnapshots(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "j")
	next, _ := m.Update(snapshotMsg{snap: fixture(t)})

	if next.(Model).checkout != "/Sites/myapp-pdf-export" {
		t.Fatalf("got %s", next.(Model).checkout)
	}
}

// TestShowsWhyRiftStoppedBeforeTheFirstSnapshot covers rift failing on its first snapshot, which leaves nothing else
// to draw.
func TestShowsWhyRiftStoppedBeforeTheFirstSnapshot(t *testing.T) {
	b, _ := backend(t)
	next, _ := New(b, "").Update(tea.WindowSizeMsg{Width: 100, Height: 30})
	next, cmd := next.Update(snapshotMsg{err: errors.New("rift: projects.yaml is broken")})

	contains(t, screen(next.(Model)), "rift dash could not read this machine.", "rift: projects.yaml is broken")

	if cmd != nil {
		t.Fatal("kept waiting for snapshots")
	}
}

// TestShowsWhyRiftStoppedAfterwards makes sure the last snapshot stays on screen once rift stops.
func TestShowsWhyRiftStoppedAfterwards(t *testing.T) {
	m, _ := dashboard(t)
	next, _ := m.Update(snapshotMsg{err: errors.New("rift exited without saying why")})

	contains(t, screen(next.(Model)), "! rift stopped", "✗ rift stopped sending snapshots: rift exited without saying why", "myapp")
}

// TestSnapshotsWaitForTheirSuccessor makes sure every snapshot asks for the next.
func TestSnapshotsWaitForTheirSuccessor(t *testing.T) {
	m, _ := dashboard(t)

	if _, cmd := m.Update(snapshotMsg{snap: snapshot.Snapshot{}}); cmd == nil {
		t.Fatal("stopped waiting")
	}
}

// TestWaitsForTheNextSnapshotAndTask covers what the dashboard's waits deliver.
func TestWaitsForTheNextSnapshotAndTask(t *testing.T) {
	m, c := dashboard(t)

	if msg := m.waitSnapshot()(); msg.(snapshotMsg).err == nil {
		t.Fatal("expected the fake's error")
	}

	c.events <- jobs.Event{Kind: jobs.Started}

	if msg := m.waitJob()(); jobs.Event(msg.(jobMsg)).Kind != jobs.Started {
		t.Fatalf("got %v", msg)
	}
}

// TestTicksMoveTheSpinner covers the tick that turns the spinner and asks for the next.
func TestTicksMoveTheSpinner(t *testing.T) {
	m, _ := dashboard(t)
	next, cmd := m.Update(tickMsg(now))

	if next.(Model).frame != 1 || cmd == nil {
		t.Fatal("the clock did not move on")
	}

	if m.tick() == nil {
		t.Fatal("no tick")
	}
}

// TestTheClockDeliversATick covers the tick itself.
func TestTheClockDeliversATick(t *testing.T) {
	m, _ := dashboard(t)

	if msg := m.tick()(); msg == nil {
		t.Fatal("no tick")
	}
}

// TestIgnoresMessagesItDoesNotKnow covers messages Bubble Tea sends that the dashboard has no use for.
func TestIgnoresMessagesItDoesNotKnow(t *testing.T) {
	m, _ := dashboard(t)

	if next, cmd := m.Update("nothing"); cmd != nil || next.(Model).checkout != m.checkout {
		t.Fatal("changed on an unknown message")
	}
}

// TestTheCommandLogKeepsItsLatestLines also makes sure a finished task asks for a fresh snapshot.
func TestTheCommandLogKeepsItsLatestLines(t *testing.T) {
	m, c := dashboard(t)

	for i := range logKeep + 3 {
		next, _ := m.Update(jobMsg(jobs.Event{Kind: jobs.Output, Text: strings.Repeat("x", i%7)}))
		m = next.(Model)
	}

	next, _ := m.Update(jobMsg(jobs.Event{Kind: jobs.Done, Text: "up · done in 1s"}))
	m = next.(Model)

	if len(m.log) != logKeep || m.running != nil || c.refreshed != 1 {
		t.Fatalf("got %d lines, %v running, %d refreshes", len(m.log), m.running, c.refreshed)
	}
}

// TestCommitsForACheckoutLeftBehindAreDropped covers commits that arrive after the person moved to another checkout.
func TestCommitsForACheckoutLeftBehindAreDropped(t *testing.T) {
	m, _ := dashboard(t)
	next, _ := m.Update(commitsMsg{dir: "/elsewhere", commits: []Commit{{SHA: "x"}}})

	if next.(Model).commits != nil {
		t.Fatal("kept commits of another checkout")
	}
}

// TestAMessageExpires covers a message in the key bar once its time is up.
func TestAMessageExpires(t *testing.T) {
	m, _ := dashboard(t)
	m.note("hello")
	m.backend.Now = func() time.Time { return now.Add(5 * time.Second) }

	if m.toastText() != "" {
		t.Fatal("the message never expired")
	}
}

// TestForegroundWithAPauseHandsTheTerminalOver covers the wrapper that waits for enter before the dashboard draws
// again.
func TestForegroundWithAPauseHandsTheTerminalOver(t *testing.T) {
	m, _ := dashboard(t)

	if m.foreground(jobs.Task{Label: "x", Dir: ".", Command: []string{"true"}}, true) == nil {
		t.Fatal("no command")
	}
}

// TestAForegroundTaskReportsWhenItEnds covers the callback Bubble Tea runs once the task hands the terminal back.
func TestAForegroundTaskReportsWhenItEnds(t *testing.T) {
	err := errors.New("x")

	if msg := ended(jobs.Task{Label: "x"})(err).(foregroundMsg); !errors.Is(msg.err, err) || msg.task.Label != "x" {
		t.Fatalf("got %+v", msg)
	}
}

// TestAForegroundTaskSaysHowItEnded covers success, an exit code, and a task that could not start.
func TestAForegroundTaskSaysHowItEnded(t *testing.T) {
	task := jobs.Task{Label: "rift trust"}
	exit := exec.Command("sh", "-c", "exit 3").Run()

	for err, want := range map[error]string{
		nil:                    "✓ rift trust",
		exit:                   "✗ rift trust exited with code 3",
		errors.New("no shell"): "✗ rift trust: no shell",
	} {
		m, c := dashboard(t)
		next, _ := m.Update(foregroundMsg{task: task, err: err})

		if next.(Model).toastText() != want || c.refreshed != 1 {
			t.Errorf("got %q", next.(Model).toastText())
		}
	}
}
