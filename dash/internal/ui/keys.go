package ui

import (
	tea "charm.land/bubbletea/v2"
)

// far is a step longer than any list, for jumping to either end.
const far = 1_000_000

const pageStep = 10

// key handles one key, then scrolls back to the top of a view the key moved away from.
func (m Model) key(key string) (tea.Model, tea.Cmd) {
	scrollStep := max(1, m.height/4)

	switch key {
	case "ctrl+d":
		m.scroll += scrollStep

		return m, nil
	case "ctrl+u":
		m.scroll = max(0, m.scroll-scrollStep)

		return m, nil
	}

	next, cmd := m.handle(key)
	model := next.(Model)

	if model.place() != m.place() {
		model.scroll = 0
	}

	return model, cmd
}

// place returns what decides which view is open, so a change of it can reset the scroll.
func (m Model) place() [5]any {
	return [5]any{m.checkout, m.tab, m.routerTab, m.focus, m.help}
}

// handle routes a key to the open modal, the help, the filter or the action it stands for.
func (m Model) handle(key string) (tea.Model, tea.Cmd) {
	if key == "ctrl+c" {
		cmd := m.quit()

		return m, cmd
	}

	switch {
	case m.modal != nil:
		return m.answer(key)
	case m.help:
		m.help = key != "?" && key != "esc" && key != "q" && key != "enter"

		return m, nil
	case m.filtering:
		return m.typeFilter(key)
	}

	switch key {
	case "1", "2", "3":
		m.focus = Panel(key[0] - '0')
	case "tab":
		m.focus = Panel(cycle(int(m.focus)-1, 1, panels) + 1)
	case "shift+tab":
		m.focus = Panel(cycle(int(m.focus)-1, -1, panels) + 1)
	case "j", "down":
		m.move(1)
	case "k", "up":
		m.move(-1)
	case "pgdown":
		m.move(pageStep)
	case "pgup":
		m.move(-pageStep)
	case "g", "home":
		m.move(-far)
	case "G", "end":
		m.move(far)
	case "]", "l", "right":
		m.switchTab(1)
	case "[", "h", "left":
		m.switchTab(-1)
	case "enter":
		if m.focus == Projects {
			m.focus = Checkouts
		}
	case "space":
		m.mark()
	case "esc":
		m.marked = map[string]bool{}
		m.filter = ""
	case "/":
		m.focus = Checkouts
		m.filtering = true
	case "f":
		m.paused = !m.paused
		m.pausedAt = len(m.logs)
	case "?":
		m.help = true
	case ":":
		m.modal = paletteModal()
	default:
		return m.act(key)
	}

	cmd := m.details()

	return m, cmd
}

// switchTab moves to the next or previous tab of the view the focus is on.
func (m *Model) switchTab(step int) {
	if m.focus == Router {
		m.routerTab = RouterTab(cycle(int(m.routerTab), step, len(routerTabNames)))

		return
	}

	m.tab = Tab(cycle(int(m.tab), step, len(tabNames)))
}

// mark marks or unmarks the selected checkout, then moves on to the next.
func (m *Model) mark() {
	c := m.selectedCheckout()
	if m.focus != Checkouts || c == nil {
		return
	}

	if m.marked[c.Root] {
		delete(m.marked, c.Root)
	} else {
		m.marked[c.Root] = true
	}

	m.move(1)
}

// typeFilter types into the checkout filter.
func (m Model) typeFilter(key string) (tea.Model, tea.Cmd) {
	switch key {
	case "esc":
		m.filtering = false
		m.filter = ""
	case "enter":
		m.filtering = false
	case "backspace":
		m.filter = dropLast(m.filter)
	case "space":
		m.filter += " "
	default:
		if len([]rune(key)) == 1 {
			m.filter += key
		}
	}

	cmd := m.details()

	return m, cmd
}

// dropLast drops the last character of a string.
func dropLast(s string) string {
	runes := []rune(s)
	if len(runes) == 0 {
		return s
	}

	return string(runes[:len(runes)-1])
}

// quit closes the dashboard, asking first while a task is still running.
func (m *Model) quit() tea.Cmd {
	m.stopFollowing()

	if !m.backend.Busy() {
		return tea.Quit
	}

	m.modal = confirmModal("Quit while a command is running?", []string{
		"Quitting stops it part-way, and drops everything queued after it.",
	}, "quit", func(m Model, modal *Modal) (Model, tea.Cmd) {
		m.backend.Stop()

		return m, tea.Quit
	})

	return nil
}
