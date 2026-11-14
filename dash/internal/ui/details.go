package ui

import (
	tea "charm.land/bubbletea/v2"
)

// details starts reading what the open tab shows for the selected checkout, if it is not read already.
//
// Logs are followed while the logs tab is open, and commits are read when the git tab opens.
func (m *Model) details() tea.Cmd {
	c := m.selectedCheckout()

	if c == nil || m.focus == Router || m.tab != Logs || !c.Up {
		m.stopFollowing()
	}

	if c == nil || m.focus == Router {
		return nil
	}

	switch {
	case m.tab == Logs && c.Up && m.followFor != c.Root:
		m.stopFollowing()
		m.followFor = c.Root
		m.logs = nil
		id, dir, argv := m.followID, c.Root, c.Logs

		return func() tea.Msg {
			stream, err := m.backend.Follow(dir, argv)

			return followMsg{id: id, stream: stream, err: err}
		}
	case m.tab == Git && m.commitsFor != c.Root:
		m.commitsFor = c.Root
		m.commits = nil
		dir := c.Root

		return func() tea.Msg { return commitsMsg{dir: dir, commits: m.backend.Commits(dir)} }
	}

	return nil
}

// stopFollowing closes the followed log, if any, which stops the command behind it, and
// drops any log still starting.
func (m *Model) stopFollowing() {
	if m.follow != nil {
		m.follow.Close()
	}

	m.follow = nil
	m.followFor = ""
	m.followID++
}

// followed starts reading a followed log, unless the person has moved on since asking for it.
func (m Model) followed(msg followMsg) (tea.Model, tea.Cmd) {
	switch {
	case msg.id != m.followID:
		if msg.err == nil {
			msg.stream.Close()
		}

		return m, nil
	case msg.err != nil:
		m.logs = []string{"Could not read its logs: " + msg.err.Error()}

		return m, nil
	}

	m.follow = msg.stream

	return m, readLine(msg.stream)
}

// readLine waits for the next line of a followed log.
func readLine(stream Stream) tea.Cmd {
	return func() tea.Msg {
		line, open := <-stream.Lines()

		return logLineMsg{stream: stream, line: line, done: !open}
	}
}

// logLine adds a line to the followed log, then waits for the next.
func (m Model) logLine(msg logLineMsg) (tea.Model, tea.Cmd) {
	if msg.stream != m.follow || msg.done {
		return m, nil
	}

	m.logs = append(m.logs, msg.line)
	if len(m.logs) > logsKeep {
		m.logs = m.logs[len(m.logs)-logsKeep:]
	}

	return m, readLine(m.follow)
}
