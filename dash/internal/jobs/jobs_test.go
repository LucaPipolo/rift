package jobs

import (
	"os"
	"reflect"
	"strings"
	"testing"
	"time"
)

// clock returns a clock that moves on by a step each time it is read.
func clock(step time.Duration) func() time.Time {
	at := time.Unix(1_800_000_000, 0)

	return func() time.Time {
		at = at.Add(step)

		return at
	}
}

// sh builds a task that runs a shell script.
func sh(label, script string) Task {
	return Task{Label: label, Dir: ".", Command: []string{"sh", "-c", script}}
}

// drain collects events until the queue has nothing left to run.
func drain(t *testing.T, q *Queue) []Event {
	t.Helper()

	var events []Event

	deadline := time.After(10 * time.Second)

	for {
		select {
		case e := <-q.Events():
			events = append(events, e)
		case <-time.After(50 * time.Millisecond):
			if !q.Busy() {
				return events
			}
		case <-deadline:
			t.Fatal("the queue never finished")
		}
	}
}

// texts lists each event's kind and text.
func texts(events []Event) [][2]any {
	out := make([][2]any, 0, len(events))

	for _, e := range events {
		out = append(out, [2]any{e.Kind, e.Text})
	}

	return out
}

// TestAnEmptyQueueIsIdle makes sure adding no tasks starts no worker that would report the queue busy.
func TestAnEmptyQueueIsIdle(t *testing.T) {
	q := New(time.Now)
	q.Add()

	if q.Busy() {
		t.Fatal("an empty chain made the queue busy")
	}
}

// TestATaskReportsItsOutputAndHowItEnded feeds a task colours, a spinner frame, a blank line and both streams, which
// only the real lines survive.
func TestATaskReportsItsOutputAndHowItEnded(t *testing.T) {
	q := New(clock(3 * time.Second))
	q.Add(sh("rift up · myapp.test", `printf '\033[32m✓\033[39m Starting containers\n\n  ⠶   Starting containers\r\nwarning: slow\n' >&2; echo done`))

	got := texts(drain(t, q))
	want := [][2]any{
		{Started, "rift up · myapp.test"},
		{Output, "✓ Starting containers"},
		{Output, "warning: slow"},
		{Output, "done"},
		{Done, "rift up · myapp.test · done in 3s"},
	}

	if !reflect.DeepEqual(got, want) {
		t.Fatalf("got %v", got)
	}
}

// TestAChainRunsInOrderAndStopsAtAFailure shows a failure dropping the rest of its own chain while the next chain
// still runs.
func TestAChainRunsInOrderAndStopsAtAFailure(t *testing.T) {
	q := New(clock(0))
	q.Add(sh("down", "exit 2"), sh("up", "true"), sh("seed", "true"))
	q.Add(sh("other", "true"))

	got := texts(drain(t, q))
	want := [][2]any{
		{Started, "down"},
		{Failed, "down · failed after 0s · skipped the 2 steps after it"},
		{Started, "other"},
		{Done, "other · done in 0s"},
	}

	if !reflect.DeepEqual(got, want) {
		t.Fatalf("got %v", got)
	}
}

// TestOneSkippedStepIsSingular covers the singular in the failure summary.
func TestOneSkippedStepIsSingular(t *testing.T) {
	q := New(clock(0))
	q.Add(sh("down", "exit 1"), sh("up", "true"))

	if got := drain(t, q)[1].Text; got != "down · failed after 0s · skipped the 1 step after it" {
		t.Fatalf("got %q", got)
	}
}

// TestACommandThatCannotStartFails covers a task whose program is missing, which fails without ever running.
func TestACommandThatCannotStartFails(t *testing.T) {
	q := New(clock(0))
	q.Add(Task{Label: "missing", Dir: ".", Command: []string{"/nonexistent/command"}})

	events := drain(t, q)

	if len(events) != 3 || events[1].Kind != Output || events[2].Text != "missing · failed after 0s" {
		t.Fatalf("got %v", texts(events))
	}
}

// TestStopEndsTheRunningTaskAndDropsTheRest covers quitting part-way: the running task is killed and nothing queued
// after it starts.
func TestStopEndsTheRunningTaskAndDropsTheRest(t *testing.T) {
	q := New(clock(0))
	q.Add(sh("long", "echo started; sleep 30"), sh("after", "true"))
	q.Add(sh("queued", "true"))

	if e := <-q.Events(); e.Kind != Started {
		t.Fatalf("got %v", e)
	}

	if e := <-q.Events(); e.Text != "started" {
		t.Fatalf("got %v", e)
	}

	q.Stop()

	got := texts(drain(t, q))
	if len(got) != 1 || got[0][0] != Failed {
		t.Fatalf("got %v", got)
	}
}

// TestATaskRunsInItsOwnSession makes sure a task has no terminal it could stop on reading: it prints its pid and its
// session id, read from /proc, which the test containers have.
func TestATaskRunsInItsOwnSession(t *testing.T) {
	needsProc(t)

	q := New(clock(0))
	q.Add(sh("session", `read -r pid _ _ _ _ sid _ < /proc/self/stat; echo "$pid $sid"`))

	events := drain(t, q)
	if len(events) != 3 {
		t.Fatalf("got %v", texts(events))
	}

	pid, sid, _ := strings.Cut(events[1].Text, " ")
	if pid == "" || pid != sid {
		t.Fatalf("pid %s runs in session %s", pid, sid)
	}
}

// TestStopWithNothingRunningDoesNothing makes sure Stop is safe to call on an idle queue, as quitting always does.
func TestStopWithNothingRunningDoesNothing(t *testing.T) {
	q := New(time.Now)
	q.Stop()

	if q.Busy() {
		t.Fatal("got busy")
	}
}

// TestAChainAddedWhileBusyRunsAfter makes sure a chain added mid-run waits for the worker already running instead of
// starting a second one.
func TestAChainAddedWhileBusyRunsAfter(t *testing.T) {
	q := New(clock(0))
	q.Add(sh("first", "sleep 0.2"))
	q.Add(sh("second", "true"))

	got := texts(drain(t, q))
	if len(got) != 4 || got[2][1] != "second" {
		t.Fatalf("got %v", got)
	}
}

// needsProc skips a test that reads /proc, which Linux has and the development container and CI run on.
func needsProc(t *testing.T) {
	t.Helper()

	if _, err := os.Stat("/proc/self/stat"); err != nil {
		t.Skip("needs /proc, which only Linux has")
	}
}
