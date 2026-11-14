// Command rift-dash is the terminal dashboard `rift dash` opens.
package main

import (
	"os"
	"path/filepath"

	"github.com/LucaPipolo/rift/dash/internal/app"
)

// main runs rift-dash on the directory it was started in, exiting with its code.
func main() {
	// Without a working directory the dashboard still runs; it just opens on no checkout in particular.
	dir, _ := os.Getwd()

	// Rift reports checkouts by their real path, so a symlinked directory would match none of them.
	if real, err := filepath.EvalSymlinks(dir); err == nil {
		dir = real
	}

	os.Exit(app.Main(os.Getenv, dir, os.Stdin, os.Stdout, os.Stderr))
}
