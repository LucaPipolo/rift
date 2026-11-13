package ui

import (
	"charm.land/lipgloss/v2"

	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// We decided to use only the terminal's own 16 colours because the dashboard then follows
// whatever theme the person has picked.
var (
	accent   = lipgloss.NewStyle().Foreground(lipgloss.Cyan)
	muted    = lipgloss.NewStyle().Foreground(lipgloss.BrightBlack)
	branchFg = lipgloss.NewStyle().Foreground(lipgloss.Magenta)
	ok       = lipgloss.NewStyle().Foreground(lipgloss.Green)
	warn     = lipgloss.NewStyle().Foreground(lipgloss.Yellow)
	bad      = lipgloss.NewStyle().Foreground(lipgloss.Red)
	bold     = lipgloss.NewStyle().Bold(true)
	plain    = lipgloss.NewStyle()
	logo     = lipgloss.NewStyle().Foreground(lipgloss.Black).Background(lipgloss.Cyan).Bold(true)
	tabOn    = lipgloss.NewStyle().Foreground(lipgloss.Black).Background(lipgloss.Cyan)
	tabDim   = lipgloss.NewStyle().Foreground(lipgloss.Black).Background(lipgloss.White)
)

// glyph returns the one-character marker for a checkout's state.
func glyph(state snapshot.State) string {
	switch state {
	case snapshot.Serving:
		return "●"
	case snapshot.Unrouted:
		return "◌"
	case snapshot.Unreachable:
		return "◐"
	case snapshot.Failed:
		return "✗"
	default:
		return "○"
	}
}

// stateStyle returns the colour of a checkout's state.
func stateStyle(state snapshot.State) lipgloss.Style {
	switch state {
	case snapshot.Serving:
		return ok
	case snapshot.Unrouted, snapshot.Unreachable:
		return warn
	case snapshot.Failed:
		return bad
	default:
		return muted
	}
}
