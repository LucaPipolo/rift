package ui

import (
	"errors"
	"reflect"
	"strings"
	"testing"
)

// TestLogsAreFollowedWhileTheirTabIsOpen covers the life of a followed log: waiting, lines with and without a source,
// pausing, and leaving the tab.
func TestLogsAreFollowedWhileTheirTabIsOpen(t *testing.T) {
	m, c := dashboard(t)
	m, cmd := press(m, "]", "]")
	next, cmd2 := m.Update(cmd())
	m = next.(Model)

	if !reflect.DeepEqual(c.follows, []string{"/Sites/myapp-search docker compose -p myapp-search logs --no-color --tail 200 --follow"}) {
		t.Fatalf("got %v", c.follows)
	}

	contains(t, screen(m), "Waiting for its logs…")

	c.stream.lines <- "app-1  | GET / 200"
	c.stream.lines <- "a line without a source"
	next, cmd2 = m.Update(cmd2())
	next, _ = next.Update(readLine(m.follow)())
	m = next.(Model)

	contains(t, screen(m), "app-1  │ GET / 200", "a line without a source", "● following")

	m, _ = press(m, "f")
	c.stream.lines <- "after the pause"
	next, _ = m.Update(readLine(m.follow)())
	contains(t, screen(next.(Model)), "❚❚ paused")

	if strings.Contains(screen(next.(Model)), "after the pause") {
		t.Fatal("drew a line that came after the pause")
	}

	m, _ = press(next.(Model), "j")
	if !c.stream.closed || m.follow != nil {
		t.Fatal("kept following after moving on")
	}

	_ = cmd2
}

// TestFollowedLogsKeepTheirLatestLines also covers a followed log that ends.
func TestFollowedLogsKeepTheirLatestLines(t *testing.T) {
	m, _ := dashboard(t)
	m.follow = &fakeStream{lines: make(chan string)}

	for range logsKeep + 2 {
		next, _ := m.Update(logLineMsg{stream: m.follow, line: "x"})
		m = next.(Model)
	}

	if len(m.logs) != logsKeep {
		t.Fatalf("got %d", len(m.logs))
	}

	if next, cmd := m.Update(logLineMsg{stream: m.follow, done: true}); cmd != nil || len(next.(Model).logs) != logsKeep {
		t.Fatal("kept reading after the end")
	}
}

// TestLogsThatCannotBeFollowedSayWhy covers Docker refusing to follow the logs.
func TestLogsThatCannotBeFollowedSayWhy(t *testing.T) {
	m, c := dashboard(t)
	c.followErr = errors.New("docker is not running")
	m, cmd := press(m, "]", "]")
	next, _ := m.Update(cmd())

	contains(t, screen(next.(Model)), "Could not read its logs: docker is not running")
}

// TestLogsForACheckoutLeftBehindAreDropped covers a log that starts after the person moved to another checkout, and
// one that failed to start meanwhile, which has nothing to close and nothing to say.
func TestLogsForACheckoutLeftBehindAreDropped(t *testing.T) {
	m, c := dashboard(t)
	stream := &fakeStream{lines: make(chan string)}
	next, cmd := m.Update(followMsg{id: m.followID - 1, stream: stream})

	if !stream.closed || cmd != nil || next.(Model).follow != nil || c.stream.closed {
		t.Fatal("followed a checkout no longer selected")
	}

	if next, _ = m.Update(followMsg{id: m.followID - 1, err: errors.New("gone")}); next.(Model).logs != nil {
		t.Fatal("reported a log no longer asked for")
	}
}

// TestLogsLeftAndReopenedFollowOneStream covers leaving the logs tab and coming back before the first log started:
// the first is closed when it arrives, and its lines are ignored.
func TestLogsLeftAndReopenedFollowOneStream(t *testing.T) {
	m, c := dashboard(t)
	m, first := press(m, "]", "]")
	m, _ = press(m, "[")
	m, second := press(m, "]")

	next, _ := m.Update(first())
	next, _ = next.Update(second())
	m = next.(Model)

	if len(c.streams) != 2 || !c.streams[0].closed || c.streams[1].closed || m.follow != c.streams[1] {
		t.Fatalf("got %+v", c.streams)
	}

	if next, cmd := m.Update(logLineMsg{stream: c.streams[0], line: "stale"}); cmd != nil || next.(Model).logs != nil {
		t.Fatal("drew a line from a stream no longer followed")
	}
}

// TestLogsOfAStoppedCheckoutAreNotFollowed makes sure no logs command starts for a checkout with nothing to follow.
func TestLogsOfAStoppedCheckoutAreNotFollowed(t *testing.T) {
	m, _ := dashboard(t)
	m, cmd := press(m, "G", "]", "]")

	if cmd != nil {
		t.Fatal("followed a stopped checkout")
	}

	contains(t, screen(m), "It is not running, so there are no logs to follow.")
}

// TestCommitsAreReadWhenTheGitTabOpens also makes sure coming back to the tab does not read them again.
func TestCommitsAreReadWhenTheGitTabOpens(t *testing.T) {
	m, c := dashboard(t)
	m, cmd := press(m, "[")

	contains(t, screen(m), "reading…")

	next, _ := m.Update(cmd())
	m = next.(Model)
	contains(t, screen(m), "abc1234 feat: rank results · 3 minutes ago")

	if _, cmd = press(m, "3", "2"); cmd != nil || len(c.commits) != 1 {
		t.Fatal("read the commits twice")
	}
}
