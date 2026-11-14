package ui

import (
	"fmt"
	"strings"

	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	"github.com/charmbracelet/x/ansi"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// The smallest terminal the dashboard draws in, and the fixed parts of its layout.
const (
	MinWidth      = 80
	MinHeight     = 24
	wideFrom      = 120
	wide          = 52
	narrow        = 44
	label         = 18
	projectsShown = 6
	routerHeight  = 4
	modalWidth    = 76
)

var spinner = []string{"⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"}

// seg is a run of text in one style, part of a row.
type seg struct {
	text  string
	style lipgloss.Style
}

// View draws the whole screen on the alternate screen.
func (m Model) View() tea.View {
	v := tea.NewView(m.render())
	v.AltScreen = true

	return v
}

// render draws the whole screen as text.
func (m Model) render() string {
	w, h := m.width, m.height

	switch {
	case w == 0:
		return ""
	case w < MinWidth || h < MinHeight:
		return fmt.Sprintf("rift dash needs at least %d×%d; this terminal is %d×%d.\n", MinWidth, MinHeight, w, h) +
			muted.Render("Make it larger, or press q to quit.")
	case !m.loaded && m.err != nil:
		return bad.Render("rift dash could not read this machine.") + "\n\n" + m.err.Error() + "\n\n" + muted.Render("Press q to quit.")
	case !m.loaded:
		return muted.Render("Reading this machine…")
	}

	body := h - 2 - (logShown + 2)
	right := w - m.sidebar()

	var detail string

	switch {
	case m.help:
		detail = m.helpView(right, body)
	case m.focus == Router:
		detail = m.routerView(right, body)
	default:
		detail = m.detail(right, body)
	}

	screen := joinRows(
		m.topBar(w),
		joinColumns(m.sidebarView(body), detail),
		m.commandLog(w),
		m.keyBar(w),
	)

	if m.modal != nil {
		screen = overlay(screen, m.modalView(), w, h)
	}

	return screen
}

// row draws segments as one line of an exact width, on a background when it is selected.
func row(segs []seg, width int, selected, focused bool) string {
	var b strings.Builder

	for _, s := range segs {
		style := s.style

		if selected {
			style = style.Bold(true)
			if focused {
				style = style.Background(lipgloss.BrightBlack)
			}
		}

		b.WriteString(style.Render(s.text))
	}

	// Rows are laid out to fill the width, so the selection's background reaches the border.
	return fit(b.String(), width)
}

// topBar draws the line across the top: the router and a count of checkouts by state.
func (m Model) topBar(width int) string {
	r := m.snap.Router
	parts := []string{logo.Render(" rift "), "  "}

	if r.Running {
		parts = append(parts, ok.Render("●"))
	} else {
		parts = append(parts, bad.Render("○"))
	}

	parts = append(parts, " "+r.Name+" ", muted.Render(r.Listening), "   ")

	if r.Trusted {
		parts = append(parts, ok.Render("✓ CA trusted"))
	} else {
		parts = append(parts, warn.Render("! CA not trusted"))
	}

	parts = append(parts, muted.Render("   │   "))

	for _, state := range snapshot.States {
		n := m.snap.Count(state)

		if n > 0 || state == snapshot.Serving {
			parts = append(parts, stateStyle(state).Render(fmt.Sprint(n)), muted.Render(" "+string(state)+"  "))
		}
	}

	if m.err != nil {
		parts = append(parts, bad.Render("  ! rift stopped"))
	}

	return fit(strings.Join(parts, ""), width)
}

// sidebar returns the sidebar's width: narrower on a narrow terminal, to leave the detail view room.
func (m Model) sidebar() int {
	if m.width < wideFrom {
		return narrow
	}

	return wide
}

// sidebarView draws the projects, checkouts and router panels.
func (m Model) sidebarView(height int) string {
	projects := min(max(len(m.snap.Projects), 1), projectsShown) + 2

	return joinRows(
		m.projectsPanel(projects),
		m.checkoutsPanel(height-projects-routerHeight),
		m.routerPanel(routerHeight),
	)
}

// panelTitle draws a numbered panel title, in the accent colour when the panel has the focus.
func (m Model) panelTitle(panel Panel, name, suffix string) string {
	if m.focus == panel {
		return accent.Render(fmt.Sprintf("[%d] ", panel)) + accent.Bold(true).Render(name) + muted.Render(suffix)
	}

	return muted.Render(fmt.Sprintf("[%d] ", panel)) + name + muted.Render(suffix)
}

// projectsPanel draws each project with its domain and how many of its checkouts run.
func (m Model) projectsPanel(height int) string {
	inside := m.sidebar() - 4
	selected := m.selectedProject()
	lines := make([]string, 0, len(m.snap.Projects))

	for _, p := range m.snap.Projects {
		up := p.UpCount()
		marker := seg{"○ ", muted}

		switch {
		case p.Error != nil:
			marker = seg{"! ", bad}
		case up > 0:
			marker = seg{"● ", ok}
		}

		domain := "cannot read rift.yaml"
		if p.Domain != nil {
			domain = *p.Domain
		}

		count := seg{fmt.Sprintf("%5s", fmt.Sprintf("%d/%d", up, len(p.Checkouts))), plain}
		if up == 0 {
			count.style = muted
		}

		lines = append(lines, row([]seg{marker, {fit(p.Name, 16), plain}, {fit(domain, inside-2-16-5), muted}, count}, inside, selected != nil && p.Root == selected.Root, m.focus == Projects))
	}

	if len(lines) == 0 {
		lines = append(lines, muted.Render("No projects yet."))
	}

	return frame(m.panelTitle(Projects, "Projects", ""), "", scroll(lines, indexOf(m.snap.Projects, selected), height-2), m.sidebar(), height, m.focus == Projects)
}

// indexOf finds the selected project's position, the first when none is selected.
func indexOf(projects []snapshot.Project, selected *snapshot.Project) int {
	for i := range projects {
		if selected != nil && projects[i].Root == selected.Root {
			return i
		}
	}

	return 0
}

// scroll keeps a window of lines that shows the selected one.
func scroll(lines []string, selected, height int) []string {
	if height <= 0 || len(lines) <= height {
		return lines
	}

	start := max(0, min(selected-height/2, len(lines)-height))

	return lines[start : start+height]
}

// checkoutsPanel draws the selected project's checkouts.
func (m Model) checkoutsPanel(height int) string {
	project := m.selectedProject()
	inside := m.sidebar() - 4
	suffix := ""

	if project != nil {
		suffix = " · " + project.Name
	}

	if m.filtering || m.filter != "" {
		cursor := ""
		if m.filtering {
			cursor = "▏"
		}

		suffix += accent.Render(" /" + m.filter + cursor)
	}

	title := m.panelTitle(Checkouts, "Checkouts", suffix)

	switch {
	case project == nil:
		return frame(title, "", wrap(muted.Render("Run `rift setup` in a project to list it here."), inside), m.sidebar(), height, m.focus == Checkouts)
	case project.Error != nil:
		return frame(title, "", wrap(bad.Render(*project.Error), inside), m.sidebar(), height, m.focus == Checkouts)
	}

	selected := m.selectedCheckout()
	shown := m.visible()
	lines := make([]string, 0, len(shown))
	index := 0

	for i, c := range shown {
		isSelected := selected != nil && c.Root == selected.Root
		if isSelected {
			index = i
		}

		lines = append(lines, row(m.checkoutRow(c, inside), inside, isSelected, m.focus == Checkouts))
	}

	if len(lines) == 0 {
		lines = append(lines, muted.Render("No checkout matches “"+m.filter+"”."))
	}

	return frame(title, "", scroll(lines, index, height-2), m.sidebar(), height, m.focus == Checkouts)
}

// checkoutRow lays out one checkout: state, name, branch, how far it is from main, and its last change.
//
// The branch takes whatever width the other columns leave.
func (m Model) checkoutRow(c snapshot.Checkout, width int) []seg {
	name := seg{c.Label, plain}

	switch {
	case m.marked[c.Root]:
		name = seg{"◆ " + c.Label, warn.Bold(true)}
	case c.Main:
		name = seg{c.Label, bold}
	}

	age := seg{"", muted}

	if c.Git.ChangedAt != nil {
		seconds := m.backend.Now().Unix() - *c.Git.ChangedAt
		age.text = ago(seconds)

		if seconds < 60 {
			age.style = accent
		}
	}

	divergence := divergence(c.Git, false)

	return []seg{
		{glyph(c.State), stateStyle(c.State)},
		{" ", plain},
		{fit(name.text, 13), name.style},
		{" ", plain},
		{fit(branchName(c), width-31), branchFg},
		{" ", plain},
		{fit(divergence, 9), muted},
		{" ", plain},
		{fmt.Sprintf("%4s", age.text), age.style},
	}
}

// branchName returns a checkout's branch, or says it is detached.
func branchName(c snapshot.Checkout) string {
	if c.Branch == nil {
		return "(detached)"
	}

	return *c.Branch
}

// divergence says how far a branch is from the main branch, and with uncommitted changes when asked.
func divergence(git snapshot.Git, explain bool) string {
	var parts []string

	if git.Ahead > 0 {
		parts = append(parts, fmt.Sprintf("↑%d", git.Ahead))
	}

	if git.Behind > 0 {
		parts = append(parts, fmt.Sprintf("↓%d", git.Behind))
	}

	if explain {
		if len(parts) == 0 {
			return "level with main"
		}

		return strings.Join(parts, " ") + " against main"
	}

	if len(git.Changes) > 0 {
		parts = append(parts, fmt.Sprintf("✎%d", len(git.Changes)))
	}

	return strings.Join(parts, " ")
}

// ago formats a duration the short way, as 8s, 3m, 2h or 4d.
func ago(seconds int64) string {
	seconds = max(0, seconds)

	switch {
	case seconds < 60:
		return fmt.Sprintf("%ds", seconds)
	case seconds < 3600:
		return fmt.Sprintf("%dm", seconds/60)
	case seconds < 86400:
		return fmt.Sprintf("%dh", seconds/3600)
	default:
		return fmt.Sprintf("%dd", seconds/86400)
	}
}

// routerPanel draws whether the router runs, its image, and what it serves or why it cannot.
func (m Model) routerPanel(height int) string {
	r := m.snap.Router
	state := ok.Render("● running")

	if !r.Running {
		state = bad.Render("○ stopped")
	}

	detail := fmt.Sprintf("%d route%s · %d on its network", len(r.Routes), plural(len(r.Routes)), len(r.Members))

	switch {
	case !r.Running:
		detail = "Start a checkout to start it."
	case !r.Answering:
		detail = "running, but its admin API is not answering"
	}

	return frame(m.panelTitle(Router, "Router", ""), "", []string{state + muted.Render(" · "+r.Image), muted.Render(detail)}, m.sidebar(), height, m.focus == Router)
}

// tabs draws a row of tab names with the open one highlighted, cutting the others to their initial when they do not fit.
func tabs(names []string, open int, focused bool, width int) string {
	parts := make([]string, 0, len(names))
	full := 0

	for _, name := range names {
		full += len(name) + 3
	}

	for i, name := range names {
		if full > width && i != open {
			name = name[:1]
		}

		switch {
		case i == open && focused:
			parts = append(parts, tabOn.Render(" "+name+" "))
		case i == open:
			parts = append(parts, tabDim.Render(" "+name+" "))
		default:
			parts = append(parts, " "+name+" ")
		}
	}

	return strings.Join(parts, muted.Render("│"))
}

// page shows the lines from an offset that fit a height, ending with how many are hidden below.
func page(lines []string, offset, height int) []string {
	if height <= 0 || len(lines) <= height {
		return lines
	}

	shown := lines[max(0, min(offset, len(lines)-height)):]

	if len(shown) <= height {
		return shown
	}

	hidden := len(shown) - (height - 1)

	return append(shown[:height-1:height-1], muted.Render(fmt.Sprintf("↓ %d more · ctrl+d", hidden)))
}

// wrap breaks text into lines of at most a width, at spaces where it can.
func wrap(text string, width int) []string {
	return strings.Split(ansi.Wrap(text, max(1, width), " "), "\n")
}

// fact draws a labelled line of the overview.
func fact(name string, value ...string) string {
	return muted.Render(fit(name, label)) + strings.Join(value, "")
}

// keyBar draws the keys that work now, and any message on the right.
func (m Model) keyBar(width int) string {
	var keys [][2]string

	switch {
	case m.modal != nil && m.modal.kind == worktreeKind:
		keys = [][2]string{{"enter", "create"}, {"esc", "cancel"}}
	case m.modal != nil && m.modal.kind == removeKind:
		keys = [][2]string{{"enter", "remove"}, {"v", "volumes too"}, {"esc", "cancel"}}
	case m.modal != nil && m.modal.kind == paletteKind:
		keys = [][2]string{{"↑↓", "choose"}, {"enter", "run"}, {"esc", "cancel"}}
	case m.modal != nil:
		keys = [][2]string{{"enter", m.modal.action}, {"esc", "cancel"}}
	case m.help:
		keys = [][2]string{{"esc", "close"}}
	case m.filtering:
		keys = [][2]string{{"enter", "keep the filter"}, {"esc", "clear it"}}
	case m.focus == Projects:
		keys = [][2]string{{"j/k", "move"}, {"enter", "checkouts"}, {"n", "new worktree"}, {"U", "up all"}, {"D", "down all"}, {":", "commands"}, {"?", "help"}, {"q", "quit"}}
	case m.focus == Router:
		keys = [][2]string{{"[ ]", "tab"}, {"H", "doctor"}, {"R", "restart router"}, {"T", "trust CA"}, {":", "commands"}, {"?", "help"}, {"q", "quit"}}
	default:
		keys = [][2]string{{"u", "up"}, {"d", "down"}, {"r", "restart"}, {"o", "open"}, {"e", "shell"}, {"n", "new"}, {"x", "remove"}, {"space", "mark"}, {"/", "filter"}, {"[ ]", "tab"}, {":", "commands"}, {"?", "help"}, {"q", "quit"}}
	}

	var b strings.Builder

	for _, k := range keys {
		b.WriteString(accent.Render(" "+k[0]) + muted.Render(" "+k[1]+" "))
	}

	if n := len(m.marked); n > 0 && m.modal == nil {
		b.WriteString(warn.Bold(true).Render(fmt.Sprintf(" %d marked ", n)))
	}

	toast := m.toastText()
	if toast == "" {
		return fit(b.String(), width)
	}

	// A message never takes more than half the bar, so the keys always show.
	toast = fit(toast, min(lipgloss.Width(toast), width/2))

	return fit(b.String(), width-lipgloss.Width(toast)) + warn.Render(toast)
}

// commandLog draws the latest lines of the command log, naming the task that runs now.
func (m Model) commandLog(width int) string {
	title := muted.Render("Command log")
	if m.running != nil {
		title += muted.Render(" · ") + accent.Render(spinner[m.frame%len(spinner)]+" "+m.running.Label)
	}

	var lines []string

	for _, l := range m.log[max(0, len(m.log)-logShown):] {
		switch l.kind {
		case jobs.Started:
			lines = append(lines, accent.Render("› ")+bold.Render(l.text))
		case jobs.Done:
			lines = append(lines, ok.Render("✓ "+l.text))
		case jobs.Failed:
			lines = append(lines, bad.Render("✗ "+l.text))
		default:
			lines = append(lines, muted.Render("  "+l.text))
		}
	}

	if len(lines) == 0 {
		lines = []string{muted.Render("Actions you run show their output here.")}
	}

	return frame(title, "", lines, width, logShown+2, m.running != nil)
}

// overlay draws a box centred over the screen, hiding what is beneath it.
func overlay(screen, box string, width, height int) string {
	lines := strings.Split(screen, "\n")
	boxLines := strings.Split(box, "\n")
	boxWidth := lipgloss.Width(box)
	x := max(0, (width-boxWidth)/2)
	y := max(0, (height-len(boxLines))/2)

	for i, b := range boxLines {
		if y+i >= len(lines) {
			break
		}

		line := lines[y+i]
		lines[y+i] = ansi.Truncate(line, x, "") + b + ansi.TruncateLeft(line, x+boxWidth, "")
	}

	return strings.Join(lines, "\n")
}
