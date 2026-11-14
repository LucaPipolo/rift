package app

import (
	"bytes"
	"context"
	"io"
	"strings"
	"sync"
	"testing"
	"time"

	tea "charm.land/bubbletea/v2"
)

// env returns an environment holding only RIFT_COMMAND.
func env(command string) func(string) string {
	return func(key string) string {
		if key == "RIFT_COMMAND" {
			return command
		}

		return ""
	}
}

// buffer is an output that tests can read while the program writes to it.
type buffer struct {
	mu sync.Mutex
	b  bytes.Buffer
}

// Write records output.
func (b *buffer) Write(p []byte) (int, error) {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.b.Write(p)
}

// String returns everything written so far.
func (b *buffer) String() string {
	b.mu.Lock()
	defer b.mu.Unlock()

	return b.b.String()
}

// TestMainRefusesToRunOnItsOwn covers rift-dash started by hand, without the command line `rift dash` passes it.
func TestMainRefusesToRunOnItsOwn(t *testing.T) {
	var stderr bytes.Buffer

	if code := Main(env(""), ".", nil, io.Discard, &stderr); code != 1 || stderr.String() != "rift-dash cannot run on its own. Run `rift dash` instead.\n" {
		t.Fatalf("got %d %q", code, stderr.String())
	}
}

// TestMainReportsARiftThatCannotStart covers a RIFT_COMMAND that names no program, as after rift moved.
func TestMainReportsARiftThatCannotStart(t *testing.T) {
	var stderr bytes.Buffer

	if code := Main(env(`["/nonexistent/rift"]`), ".", nil, io.Discard, &stderr); code != 1 || !strings.HasPrefix(stderr.String(), "rift-dash could not start rift") {
		t.Fatalf("got %d %q", code, stderr.String())
	}
}

// TestMainShowsTheMachineUntilThePersonQuits is the one test of the whole program: a real Bubble Tea loop, the fake
// rift sending snapshots, and a key to quit.
func TestMainShowsTheMachineUntilThePersonQuits(t *testing.T) {
	input, keys := io.Pipe()
	var output buffer
	done := make(chan int)

	go func() {
		done <- Main(env(`["sh", "../rift/testdata/fake-rift"]`), ".", input, &output, io.Discard, tea.WithWindowSize(120, 40))
	}()

	deadline := time.Now().Add(10 * time.Second)

	for !strings.Contains(output.String(), "myapp.test") {
		select {
		case code := <-done:
			t.Fatalf("exited with %d before drawing", code)
		case <-time.After(20 * time.Millisecond):
		}

		if time.Now().After(deadline) {
			t.Fatalf("never drew the snapshot:\n%s", output.String())
		}
	}

	_, _ = keys.Write([]byte("q"))

	select {
	case code := <-done:
		if code != 0 {
			t.Fatalf("exited with %d", code)
		}
	case <-time.After(10 * time.Second):
		t.Fatal("never quit")
	}
}

// TestMainReportsAProgramThatFails covers Bubble Tea stopping on its own, here through a cancelled context.
func TestMainReportsAProgramThatFails(t *testing.T) {
	ctx, cancel := context.WithCancel(context.Background())
	cancel()

	var stderr bytes.Buffer

	if code := Main(env(`["sh", "../rift/testdata/fake-rift"]`), ".", strings.NewReader(""), io.Discard, &stderr, tea.WithContext(ctx)); code != 1 || stderr.String() == "" {
		t.Fatalf("got %d %q", code, stderr.String())
	}
}
