package ui

import (
	"fmt"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// act runs the action a key stands for.
func (m Model) act(key string) (tea.Model, tea.Cmd) {
	var cmd tea.Cmd

	switch key {
	case "q":
		cmd = m.quit()
	case "o":
		cmd = m.open()
	case "y":
		cmd = m.copyURL()
	case "u":
		cmd = m.up("up", m.targets())
	case "s":
		cmd = m.up("setup", m.targets())
	case "r":
		m.restart()
	case "d":
		m.askDown(m.targets())
	case "U":
		cmd = m.upAll()
	case "D":
		if p := m.selectedProject(); p != nil {
			m.askDown(p.Checkouts)
		}
	case "n":
		m.newWorktree()
	case "x":
		m.askRemove()
	case "e":
		cmd = m.shell()
	case "E":
		cmd = m.editor()
	case "T":
		cmd = m.trust()
	case "R":
		m.askRestartRouter()
	case "H":
		m.doctor()
	}

	return m, cmd
}

// task builds the task that runs a rift command in a checkout.
func (m Model) task(command string, c snapshot.Checkout) jobs.Task {
	return jobs.Task{Label: "rift " + command + " · " + c.Domain, Dir: c.Root, Command: m.backend.Rift.Command(command)}
}

// up starts checkouts, each in a chain of its own so one failing never stops another.
//
// While the router's CA is not trusted, `up` may ask for a password to trust it,
// which a background task cannot answer, so it runs in the foreground instead.
func (m *Model) up(command string, targets []snapshot.Checkout) tea.Cmd {
	m.marked = map[string]bool{}

	if len(targets) == 0 {
		return nil
	}

	if !m.snap.Router.Trusted {
		if len(targets) > 1 {
			m.note("The CA is not trusted yet, so this starts one checkout, in the foreground.")
		}

		c := targets[0]

		return m.foreground(jobs.Task{Label: "rift " + command + " · " + c.Domain, Dir: c.Root, Command: m.backend.Rift.Interactive(command)}, true)
	}

	for _, c := range targets {
		m.backend.Run(m.task(command, c))
	}

	return nil
}

// upAll starts every checkout of the selected project that is not running.
func (m *Model) upAll() tea.Cmd {
	var stopped []snapshot.Checkout

	if p := m.selectedProject(); p != nil {
		for _, c := range p.Checkouts {
			if !c.Up {
				stopped = append(stopped, c)
			}
		}
	}

	if len(stopped) == 0 {
		m.note("Every checkout is already running.")

		return nil
	}

	return m.up("up", stopped)
}

// restart stops and starts the targets again.
func (m *Model) restart() {
	if !m.snap.Router.Trusted {
		m.note("Trust the router's CA first: press T.")

		return
	}

	for _, c := range m.targets() {
		m.backend.Run(m.task("down", c), m.task("up", c))
	}

	m.marked = map[string]bool{}
}

// askDown asks before stopping the checkouts that have containers.
func (m *Model) askDown(checkouts []snapshot.Checkout) {
	var running []snapshot.Checkout

	for _, c := range checkouts {
		if len(c.Containers) > 0 {
			running = append(running, c)
		}
	}

	m.marked = map[string]bool{}

	if len(running) == 0 {
		m.note("Nothing to stop.")

		return
	}

	title := fmt.Sprintf("Stop %d checkouts?", len(running))
	if len(running) == 1 {
		title = "Stop " + running[0].Label + "?"
	}

	lines := make([]string, 0, len(running)+3)
	for _, c := range running {
		lines = append(lines, "  "+c.Domain)
	}

	lines = append(lines, "", "Their containers stop and their routes are removed.", "Volumes and .env stay, and every other checkout keeps serving.")

	m.modal = confirmModal(title, lines, "stop", func(m Model, modal *Modal) (Model, tea.Cmd) {
		for _, c := range running {
			m.backend.Run(m.task("down", c))
		}

		return m, nil
	})
}

// newWorktree asks for the branch of a new worktree.
func (m *Model) newWorktree() {
	p := m.selectedProject()

	switch {
	case p == nil || p.Error != nil:
		m.note("Fix this project's rift.yaml first.")
	case !m.snap.Router.Trusted:
		// Its setup runs in the background, where a password prompt cannot be answered.
		m.note("Trust the router's CA first: press T.")
	default:
		m.modal = &Modal{kind: worktreeKind, title: "New worktree of " + p.Name, action: "create", project: *p}
	}
}

// askRemove asks before removing the selected worktree.
func (m *Model) askRemove() {
	c := m.selectedCheckout()

	switch {
	case c == nil:
		return
	case c.Main:
		m.note("The main checkout cannot be removed.")

		return
	}

	branch := "(detached)"
	if c.Branch != nil {
		branch = *c.Branch
	}

	keep := "The branch " + branch + " and its commits stay."
	if n := len(c.Git.Changes); n > 0 {
		keep = fmt.Sprintf("Its %d uncommitted file%s will be lost. %s", n, plural(n), keep)
	}

	checkout := *c
	project := m.selectedProject().Root

	m.modal = &Modal{
		kind:     removeKind,
		title:    "Remove the " + c.Label + " worktree?",
		lines:    []string{"Stops " + c.Domain + ", removes its route, then runs", "git worktree remove on " + c.Root + ".", "", keep},
		action:   "remove",
		checkout: checkout,
	}

	m.modal.confirm = func(m Model, modal *Modal) (Model, tea.Cmd) {
		var chain []jobs.Task

		if len(checkout.Containers) > 0 {
			chain = append(chain, m.task("down", checkout))
		}

		if modal.option {
			chain = append(chain, jobs.Task{
				Label:   "remove volumes · " + checkout.ComposeName,
				Dir:     checkout.Root,
				Command: []string{"docker", "compose", "-p", checkout.ComposeName, "down", "--volumes"},
			})
		}

		remove := []string{"git", "worktree", "remove"}
		if len(checkout.Git.Changes) > 0 {
			remove = append(remove, "--force")
		}

		chain = append(chain, jobs.Task{Label: "git worktree remove · " + checkout.Label, Dir: project, Command: append(remove, checkout.Root)})
		m.backend.Run(chain...)
		m.checkout = ""

		return m, nil
	}
}

// open opens the selected checkout in the browser.
//
// It runs as a command, since xdg-open can take its time and the dashboard must not wait for it.
func (m *Model) open() tea.Cmd {
	c := m.selectedCheckout()

	switch {
	case c == nil:
	case !c.Up:
		m.note(c.Label + " is not running, so there is nothing to open.")
	default:
		url := c.URLs[0]

		return func() tea.Msg { return openedMsg{url: url, err: m.backend.Open(url)} }
	}

	return nil
}

// opened says how opening a URL went.
func (m *Model) opened(msg openedMsg) {
	if msg.err != nil {
		m.note("Could not open " + msg.url + ": " + msg.err.Error())
	} else {
		m.note("Opened " + msg.url)
	}
}

// copyURL copies the selected checkout's URL.
func (m *Model) copyURL() tea.Cmd {
	c := m.selectedCheckout()
	if c == nil {
		return nil
	}

	m.note("Copied " + c.URLs[0])

	return m.backend.Copy(c.URLs[0])
}

// shell opens a shell in the selected checkout's app container.
func (m *Model) shell() tea.Cmd {
	c := m.selectedCheckout()

	switch {
	case c == nil:
		return nil
	case !c.Up:
		m.note(c.Label + " is not running, so there is no container to open a shell in.")

		return nil
	}

	return m.foreground(jobs.Task{Label: "shell · " + c.AppService + " in " + c.Label, Dir: c.Root, Command: c.Shell}, false)
}

// editor opens the selected checkout in the person's editor.
func (m *Model) editor() tea.Cmd {
	c := m.selectedCheckout()
	if c == nil {
		return nil
	}

	return m.foreground(jobs.Task{Label: "editor · " + c.Label, Dir: c.Root, Command: []string{"sh", "-c", `exec ${VISUAL:-${EDITOR:-vi}} .`}}, false)
}

// trust trusts the router's CA, in the foreground for the password prompt.
func (m *Model) trust() tea.Cmd {
	if !m.snap.Router.Running {
		m.note("Start a checkout first, so the router is running and has issued its CA.")

		return nil
	}

	return m.foreground(jobs.Task{Label: "rift trust", Dir: m.dir(), Command: m.backend.Rift.Interactive("trust")}, true)
}

// askRestartRouter asks before restarting the router every project shares.
func (m *Model) askRestartRouter() {
	name := m.snap.Router.Name
	outage := fmt.Sprintf("Its %d routes stop answering for a few seconds, then come back on their own.", len(m.snap.Router.Routes))

	if len(m.snap.Router.Routes) == 1 {
		outage = "Its 1 route stops answering for a few seconds, then comes back on its own."
	}

	m.modal = confirmModal("Restart "+name+"?", []string{
		"Every project on this machine routes through it.",
		outage,
	}, "restart", func(m Model, modal *Modal) (Model, tea.Cmd) {
		m.backend.Run(jobs.Task{Label: "rift router:restart · " + name, Dir: m.dir(), Command: m.backend.Rift.Command("router:restart")})

		return m, nil
	})
}

// doctor checks this machine's rift setup, showing the report in the router's doctor tab.
func (m *Model) doctor() {
	m.focus = Router
	m.routerTab = Doctor
	m.backend.Run(jobs.Task{Label: "rift doctor", Dir: m.dir(), Command: m.backend.Rift.Command("doctor")})
}

// dir returns a directory for a command that does not belong to a checkout: the selected project's.
func (m Model) dir() string {
	if p := m.selectedProject(); p != nil {
		return p.Root
	}

	return "."
}

// plural returns the plural ending for a count.
func plural(n int) string {
	if n == 1 {
		return ""
	}

	return "s"
}
