// Package app starts rift-dash with what the process was given.
package app

import (
	"fmt"
	"io"
)

// Main runs rift-dash and returns its exit code.
func Main(getenv func(string) string, stdout, stderr io.Writer) int {
	if getenv("RIFT_COMMAND") == "" {
		_, _ = fmt.Fprintln(stderr, "rift-dash is started by rift. Run `rift dash` instead.")

		return 1
	}

	_, _ = fmt.Fprintln(stdout, "rift-dash")

	return 0
}
