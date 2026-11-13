package app

import (
	"bytes"
	"testing"
)

// TestMainRefusesToRunOnItsOwn checks that rift-dash points to `rift dash` when started by hand.
func TestMainRefusesToRunOnItsOwn(t *testing.T) {
	var stdout, stderr bytes.Buffer

	code := Main(func(string) string { return "" }, &stdout, &stderr)

	if code != 1 || stderr.String() != "rift-dash is started by rift. Run `rift dash` instead.\n" {
		t.Fatalf("got exit %d and %q", code, stderr.String())
	}
}

// TestMainRunsWhenStartedByRift checks that rift-dash runs once rift has said how to reach it.
func TestMainRunsWhenStartedByRift(t *testing.T) {
	var stdout, stderr bytes.Buffer

	code := Main(func(string) string { return `["rift"]` }, &stdout, &stderr)

	if code != 0 || stdout.String() != "rift-dash\n" {
		t.Fatalf("got exit %d and %q", code, stdout.String())
	}
}
