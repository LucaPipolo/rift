package ui

import (
	"strings"

	"charm.land/lipgloss/v2"
	"github.com/charmbracelet/x/ansi"
)

// fit cuts or pads a styled line to exactly a width, ending a cut with an ellipsis.
func fit(line string, width int) string {
	if width <= 0 {
		return ""
	}

	if lipgloss.Width(line) > width {
		line = ansi.Truncate(line, width, "…")
	}

	return line + strings.Repeat(" ", width-lipgloss.Width(line))
}

// frame draws a box of an exact size with a title in its top border, filling it with lines.
//
// Every line is cut or padded to the inside width, so nothing a line holds can push the
// border out of place.
func frame(title, right string, lines []string, width, height int, focused bool) string {
	if width < 4 || height < 2 {
		return ""
	}

	border := muted
	if focused {
		border = accent
	}

	inside := width - 4
	top := border.Render("╭") + fitTitle(title, right, width-2, border) + border.Render("╮")

	rows := make([]string, 0, height)
	rows = append(rows, top)

	for i := range height - 2 {
		line := ""
		if i < len(lines) {
			line = lines[i]
		}

		rows = append(rows, border.Render("│")+" "+fit(line, inside)+" "+border.Render("│"))
	}

	rows = append(rows, border.Render("╰"+strings.Repeat("─", width-2)+"╯"))

	return strings.Join(rows, "\n")
}

// fitTitle lays a title on the left of a top border and a note on its right, filling the rest with rule.
func fitTitle(title, right string, width int, border lipgloss.Style) string {
	if title != "" {
		title = " " + title + " "
	}

	if right != "" {
		right = " " + right + " "
	}

	if lipgloss.Width(right) > width/2 {
		right = ""
	}

	title = fit(title, min(lipgloss.Width(title), width-lipgloss.Width(right)-1))
	rule := width - lipgloss.Width(title) - lipgloss.Width(right)

	return border.Render("─") + title + border.Render(strings.Repeat("─", max(0, rule-1))) + right
}

// joinColumns sets blocks of lines side by side.
func joinColumns(blocks ...string) string {
	return lipgloss.JoinHorizontal(lipgloss.Top, blocks...)
}

// joinRows stacks blocks of lines.
func joinRows(blocks ...string) string {
	return lipgloss.JoinVertical(lipgloss.Left, blocks...)
}
