// Command rift-dash is the terminal dashboard `rift dash` opens.
package main

import (
	"os"

	"github.com/LucaPipolo/rift/dash/internal/app"
)

// main runs rift-dash and exits with its code.
func main() {
	os.Exit(app.Main(os.Getenv, os.Stdout, os.Stderr))
}
