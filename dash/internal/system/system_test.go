package system

import (
	"os"
	"os/exec"
	"reflect"
	"strings"
	"syscall"
	"testing"
	"time"
)

// repository creates a git repository with two commits.
func repository(t *testing.T) string {
	t.Helper()

	dir := t.TempDir()

	for _, args := range [][]string{
		{"init", "-q"},
		{"-c", "user.name=t", "-c", "user.email=t@t", "commit", "-q", "--allow-empty", "-m", "first"},
		{"-c", "user.name=t", "-c", "user.email=t@t", "commit", "-q", "--allow-empty", "-m", "feat: second"},
	} {
		cmd := exec.Command("git", args...)
		cmd.Dir = dir

		if out, err := cmd.CombinedOutput(); err != nil {
			t.Fatalf("git %v: %v %s", args, err, out)
		}
	}

	return dir
}

// TestCommitsReadsTheLatestFirst runs git on a real repository in a temporary directory.
func TestCommitsReadsTheLatestFirst(t *testing.T) {
	commits := Commits(repository(t))

	if len(commits) != 2 || commits[0].Subject != "feat: second" || len(commits[0].SHA) < 7 || commits[0].When == "" {
		t.Fatalf("got %+v", commits)
	}
}

// TestCommitsOutsideARepositoryAreNone makes sure a failing git gives an empty list, which the git tab shows as none,
// not as still reading.
func TestCommitsOutsideARepositoryAreNone(t *testing.T) {
	if got := Commits(t.TempDir()); got == nil || len(got) != 0 {
		t.Fatalf("got %+v", got)
	}
}

// TestFollowDeliversBothStreamsThenEnds makes sure stderr is followed too, since Compose prints some logs there.
func TestFollowDeliversBothStreamsThenEnds(t *testing.T) {
	stream, err := Follow(".", []string{"sh", "-c", "echo out; echo err >&2"})
	if err != nil {
		t.Fatal(err)
	}

	var lines []string
	for line := range stream.Lines() {
		lines = append(lines, line)
	}

	if len(lines) != 2 {
		t.Fatalf("got %v", lines)
	}
}

// TestFollowStopsOnClose covers leaving the logs tab while the command still runs, and closing twice.
func TestFollowStopsOnClose(t *testing.T) {
	stream, err := Follow(".", []string{"sh", "-c", "echo started; sleep 30"})
	if err != nil {
		t.Fatal(err)
	}

	if <-stream.Lines() != "started" {
		t.Fatal("no first line")
	}

	stream.Close()
	stream.Close()

	select {
	case _, open := <-stream.Lines():
		if open {
			t.Fatal("kept delivering")
		}
	case <-time.After(5 * time.Second):
		t.Fatal("never stopped")
	}
}

// TestFollowEndsTheCommandWithLinesStillWaiting covers leaving the logs tab while more lines are waiting than the
// dashboard reads, which used to leave the command and its readers waiting on each other forever.
func TestFollowEndsTheCommandWithLinesStillWaiting(t *testing.T) {
	followed, err := Follow(".", []string{"sh", "-c", "while :; do echo line; done"})
	if err != nil {
		t.Fatal(err)
	}

	s := followed.(*stream)

	deadline := time.Now().Add(5 * time.Second)
	for len(s.lines) < linesWaiting {
		if time.Now().After(deadline) {
			t.Fatal("the lines never filled up")
		}

		time.Sleep(10 * time.Millisecond)
	}

	s.Close()
	waitExited(t, s)
}

// TestFollowLeavesAnExitedCommandAlone makes sure Close signals nothing once the command is reaped, since its process
// group id may already belong to another program.
func TestFollowLeavesAnExitedCommandAlone(t *testing.T) {
	followed, err := Follow(".", []string{"true"})
	if err != nil {
		t.Fatal(err)
	}

	s := followed.(*stream)
	waitExited(t, s)

	var signalled []int

	kill = func(pid int, _ syscall.Signal) error {
		signalled = append(signalled, pid)

		return nil
	}
	t.Cleanup(func() { kill = syscall.Kill })

	s.Close()

	if len(signalled) != 0 {
		t.Fatalf("signalled %v", signalled)
	}
}

// TestFollowRunsTheCommandInItsOwnSession makes sure the command has no terminal it could stop on reading: it
// prints its pid and its session id, read from /proc, which the test containers have.
func TestFollowRunsTheCommandInItsOwnSession(t *testing.T) {
	needsProc(t)

	followed, err := Follow(".", []string{"sh", "-c", `read -r pid _ _ _ _ sid _ < /proc/self/stat; echo "$pid $sid"`})
	if err != nil {
		t.Fatal(err)
	}

	pid, sid, _ := strings.Cut(<-followed.Lines(), " ")
	if pid == "" || pid != sid {
		t.Fatalf("pid %s runs in session %s", pid, sid)
	}
}

// waitExited fails unless a followed command is reaped within a few seconds.
func waitExited(t *testing.T, s *stream) {
	t.Helper()

	deadline := time.Now().Add(5 * time.Second)

	for {
		s.mu.Lock()
		exited := s.exited
		s.mu.Unlock()

		if exited {
			return
		}

		if time.Now().After(deadline) {
			t.Fatal("the command was never reaped")
		}

		time.Sleep(10 * time.Millisecond)
	}
}

// TestFollowReportsACommandThatCannotStart covers a logs command whose program is missing.
func TestFollowReportsACommandThatCannotStart(t *testing.T) {
	if _, err := Follow(".", []string{"/nonexistent/command"}); err == nil {
		t.Fatal("expected an error")
	}
}

// TestOpenerUsesTheSystemsCommand covers macOS and Linux.
func TestOpenerUsesTheSystemsCommand(t *testing.T) {
	for goos, want := range map[string]string{"darwin": "open", "linux": "xdg-open"} {
		var ran []string

		_ = Opener(goos, func(name string, args ...string) error {
			ran = append([]string{name}, args...)

			return nil
		})("https://myapp.test")

		if !reflect.DeepEqual(ran, []string{want, "https://myapp.test"}) {
			t.Errorf("%s: got %v", goos, ran)
		}
	}
}

// TestCopierPipesToPbcopyOnMacOS covers the one system whose Terminal ignores the clipboard escape sequence.
func TestCopierPipesToPbcopyOnMacOS(t *testing.T) {
	var piped []string

	cmd := Copier("darwin", func(text, name string) error {
		piped = []string{text, name}

		return nil
	})("https://myapp.test")

	if cmd != nil || !reflect.DeepEqual(piped, []string{"https://myapp.test", "pbcopy"}) {
		t.Fatalf("got %v", piped)
	}
}

// TestCopierAsksTheTerminalElsewhere covers every other system, where the terminal sets the clipboard.
func TestCopierAsksTheTerminalElsewhere(t *testing.T) {
	if cmd := Copier("linux", nil)("https://myapp.test"); cmd == nil || cmd() == nil {
		t.Fatal("did not ask the terminal")
	}
}

// TestRunAndPipeWaitForTheCommand runs harmless commands, since the real ones open a browser or the clipboard.
func TestRunAndPipeWaitForTheCommand(t *testing.T) {
	if err := Run("true"); err != nil {
		t.Fatal(err)
	}

	if err := Pipe("text", "cat"); err != nil {
		t.Fatal(err)
	}
}

// needsProc skips a test that reads /proc, which Linux has and the development container and CI run on.
func needsProc(t *testing.T) {
	t.Helper()

	if _, err := os.Stat("/proc/self/stat"); err != nil {
		t.Skip("needs /proc, which only Linux has")
	}
}
