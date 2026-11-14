package ui

import (
	"fmt"
	"strings"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// detail draws the selected checkout's detail view, on the open tab.
func (m Model) detail(width, height int) string {
	c := m.selectedCheckout()
	inside := width - 4
	domain := ""

	if c != nil {
		domain = c.Domain
	}

	title := tabs(tabNames, int(m.tab), m.focus == Checkouts, inside-len(domain)-4)

	if c == nil {
		return frame(title, "", []string{muted.Render("Nothing selected.")}, width, height, m.focus == Checkouts)
	}

	var lines []string

	switch m.tab {
	case Containers:
		lines = containersTab(*c)
	case Logs:
		lines = m.logsTab(*c, inside, height-2)
	case Env:
		lines = envTab(*c)
	case Hooks:
		lines = hooksTab(*c, inside)
	case Git:
		lines = m.gitTab(*c)
	default:
		lines = m.overview(*c, inside)
	}

	// The logs tab keeps its own latest lines in view instead of scrolling.
	if m.tab != Logs {
		lines = page(lines, m.scroll, height-2)
	}

	return frame(title, accent.Render(domain), lines, width, height, m.focus == Checkouts)
}

// overview lists a checkout's URLs and what it is, after advice when it is not serving.
func (m Model) overview(c snapshot.Checkout, width int) []string {
	lines := advice(c, width)
	lines = append(lines, muted.Render("urls"))

	for _, url := range c.URLs {
		switch {
		case c.State == snapshot.Serving:
			lines = append(lines, ok.Render("✓ ")+accent.Render(url))
		case c.Up:
			lines = append(lines, warn.Render("– ")+accent.Render(url))
		default:
			lines = append(lines, muted.Render("– "+url))
		}
	}

	state := string(c.State)

	switch c.State {
	case snapshot.Unrouted:
		state = "running, not routed"
	case snapshot.Unreachable:
		state = "running, unreachable"
	case snapshot.Failed:
		state = fmt.Sprintf("exited (%d)", *c.ExitCode)
	}

	changes := "nothing"
	if n := len(c.Git.Changes); n > 0 {
		changes = fmt.Sprintf("%d file%s", n, plural(n))
	}

	if c.Git.ChangedAt != nil {
		changes += muted.Render(" · last change " + ago(m.backend.Now().Unix()-*c.Git.ChangedAt) + " ago")
	}

	lines = append(lines,
		"",
		muted.Render("checkout"),
		fact("state", stateStyle(c.State).Render(glyph(c.State)+" "+state)),
		fact("branch", branchFg.Render(branchName(c)), " ", muted.Render(divergence(c.Git, true))),
		fact("path", c.Root),
		fact("compose project", c.ComposeName),
		fact("app", fmt.Sprintf("%s:%d", c.AppService, c.AppPort), muted.Render(" · preset "+c.Preset)),
		fact("uncommitted", changes),
	)

	if len(c.Ports) > 0 {
		lines = append(lines, "", muted.Render("host ports"))

		for _, p := range c.Ports {
			switch {
			case p.Value == nil:
				lines = append(lines, fact(p.Key, warn.Render("not set: press s")))
			case *p.Value != fmt.Sprint(p.Default):
				lines = append(lines, fact(p.Key, *p.Value, muted.Render(fmt.Sprintf(" (default %d was taken)", p.Default))))
			default:
				lines = append(lines, fact(p.Key, *p.Value))
			}
		}
	}

	return lines
}

// advice says what is wrong with a checkout that is not serving, and what to press.
func advice(c snapshot.Checkout, width int) []string {
	var text string

	switch c.State {
	case snapshot.Unrouted:
		text = warn.Render("Running, but the router has no route for it. Press u to route it.")
	case snapshot.Unreachable:
		text = warn.Render(c.AppService + " is not on the " + c.Network + " network, so the router cannot reach it. Add the network to the service in your compose file, then press r to restart it.")
	case snapshot.Failed:
		text = bad.Render(fmt.Sprintf("%s exited with code %d. Its logs tab says why. Press u to start it again.", c.AppService, *c.ExitCode))
	case snapshot.Stopped:
		text = muted.Render("Stopped. Press u to start it, or s to write its .env first.")
	default:
		return nil
	}

	return append(wrap(text, width), "")
}

// containersTab lists a checkout's containers.
func containersTab(c snapshot.Checkout) []string {
	if len(c.Containers) == 0 {
		return []string{muted.Render("No containers for " + c.ComposeName + ". Press u to create them.")}
	}

	lines := []string{muted.Underline(true).Render(fit("service", 16)) + " " + muted.Underline(true).Render(fit("state", 14)) + " " + muted.Underline(true).Render(fit("health", 10)) + " " + muted.Underline(true).Render("status")}

	for _, ct := range c.Containers {
		state := ok.Render(fit("running", 14))

		if !ct.Running {
			text, style := ct.State, muted

			if ct.ExitCode != nil {
				text = fmt.Sprintf("exited (%d)", *ct.ExitCode)
				if *ct.ExitCode != 0 {
					style = bad
				}
			}

			state = style.Render(fit(text, 14))
		}

		health := muted.Render(fit("–", 10))

		if ct.Health != nil {
			switch *ct.Health {
			case "healthy":
				health = ok.Render(fit("healthy", 10))
			case "unhealthy":
				health = bad.Render(fit("unhealthy", 10))
			default:
				health = warn.Render(fit(*ct.Health, 10))
			}
		}

		lines = append(lines, fit(ct.Service, 16)+" "+state+" "+health+" "+muted.Render(ct.Status))
	}

	return lines
}

// logsTab shows the latest lines of the followed log that fit.
func (m Model) logsTab(c snapshot.Checkout, width, height int) []string {
	if !c.Up {
		return []string{muted.Render("It is not running, so there are no logs to follow. Press u to start it.")}
	}

	if len(m.logs) == 0 {
		return []string{muted.Render("Waiting for its logs…")}
	}

	status := ok.Render("● following") + muted.Render(" · f pauses")
	if m.paused {
		status = warn.Render("❚❚ paused") + muted.Render(" · f follows again")
	}

	lines := make([]string, 0, len(m.logs))

	for _, line := range m.logs {
		source, message, found := strings.Cut(line, " | ")
		if found {
			lines = append(lines, accent.Render(source+" │")+" "+message)
		} else {
			lines = append(lines, line)
		}
	}

	shown := lines[max(0, len(lines)-(height-1)):]
	if m.paused {
		shown = lines[max(0, min(len(lines), m.pausedAt)-(height-1)):min(len(lines), m.pausedAt)]
	}

	return append([]string{fit(status, width)}, shown...)
}

// envTab lists every .env value Rift manages, and where it comes from.
func envTab(c snapshot.Checkout) []string {
	lines := []string{muted.Underline(true).Render(fit("key", 24)) + " " + muted.Underline(true).Render(fit("value", 34)) + " " + muted.Underline(true).Render("from")}

	for _, v := range c.Env {
		value := warn.Render(fit("not set: press s", 34))
		if v.Value != nil {
			value = fit(*v.Value, 34)
		}

		lines = append(lines, fit(v.Key, 24)+" "+value+" "+muted.Render(v.Source))
	}

	return lines
}

// hooksTab lists what runs at each stage of `rift up` and `rift down`.
func hooksTab(c snapshot.Checkout, width int) []string {
	var lines []string

	for _, stage := range []string{"before_up", "after_up", "before_down", "after_down"} {
		lines = append(lines, muted.Render(stage))
		found := false

		for _, h := range c.Hooks {
			if h.Stage != stage {
				continue
			}

			found = true
			name, details := h.Run, []string{}

			if h.Label != nil {
				name = *h.Label
				details = append(details, h.Run)
			}

			if h.OnHost {
				details = append(details, "on the host")
			}

			if h.UnlessExists != nil {
				details = append(details, "unless "+*h.UnlessExists+" exists")
			}

			extra := ""
			if len(details) > 0 {
				extra = muted.Render(" · " + strings.Join(details, " · "))
			}

			lines = append(lines, wrap(accent.Render("$ ")+name+extra, width)...)
		}

		if !found {
			lines = append(lines, muted.Render("  none"))
		}

		lines = append(lines, "")
	}

	return append(lines, wrap(muted.Render("Hooks come from rift.yaml and run inside "+c.AppService+" unless they run on the host."), width)...)
}

// gitTab shows a checkout's branch, its uncommitted files and its latest commits.
func (m Model) gitTab(c snapshot.Checkout) []string {
	lines := []string{
		fact("branch", branchFg.Render(branchName(c)), " ", muted.Render(divergence(c.Git, true))),
		"",
		muted.Render(fmt.Sprintf("uncommitted (%d)", len(c.Git.Changes))),
	}

	for _, path := range c.Git.Changes {
		lines = append(lines, warn.Render("  ✎ ")+path)
	}

	if len(c.Git.Changes) == 0 {
		lines = append(lines, muted.Render("  clean"))
	}

	lines = append(lines, "", muted.Render("recent commits"))

	switch {
	case m.commitsFor != c.Root || m.commits == nil:
		lines = append(lines, muted.Render("  reading…"))
	case len(m.commits) == 0:
		lines = append(lines, muted.Render("  none"))
	}

	for _, commit := range m.commits {
		lines = append(lines, "  "+warn.Render(commit.SHA)+" "+commit.Subject+muted.Render(" · "+commit.When))
	}

	return lines
}

// routerView draws the router's routes, doctor report or settings, in place of the detail view.
func (m Model) routerView(width, height int) string {
	r := m.snap.Router
	inside := width - 4

	var lines []string

	switch m.routerTab {
	case Doctor:
		lines = m.doctorTab(inside)
	case Config:
		trusted := ok.Render("✓ trusted by this machine")
		if !r.Trusted {
			trusted = warn.Render("! not trusted: press T")
		}

		lines = []string{
			fact("name", r.Name),
			fact("image", r.Image),
			fact("listens on", r.Binding),
			fact("admin API", r.Admin),
			fact("network", r.Network),
			fact("CA", trusted),
			fact("config file", r.ConfigPath),
			"",
		}
		lines = append(lines, wrap(muted.Render("Docker cannot change a running container's ports, so a new binding recreates the router."), inside)...)
	default:
		lines = routesTab(r)
	}

	return frame(tabs(routerTabNames, int(m.routerTab), true, inside-len(r.Name)-4), accent.Render(r.Name), page(lines, m.scroll, height-2), width, height, true)
}

// routesTab lists every hostname the router answers, and where it sends it.
func routesTab(r snapshot.Router) []string {
	switch {
	case !r.Running:
		return []string{muted.Render("The router is not running.")}
	case len(r.Routes) == 0:
		return []string{muted.Render("Nothing registered.")}
	}

	var lines []string

	for _, route := range r.Routes {
		marker, note := ok.Render("●"), ""
		if !route.Reachable {
			marker, note = warn.Render("◐"), "  (unreachable)"
		}

		for i, host := range route.Hosts {
			style := accent
			if i > 0 {
				style = muted
			}

			lines = append(lines, marker+" "+style.Render(fit(host, 40))+" "+muted.Render("→ "+route.Upstream+note))
		}
	}

	return lines
}

// doctorTab shows the latest `rift doctor` report from the command log.
func (m Model) doctorTab(width int) []string {
	start := -1

	for i, l := range m.log {
		if l.kind == jobs.Started && l.text == "rift doctor" {
			start = i
		}
	}

	if start < 0 {
		return wrap(muted.Render("Press H to check this machine: Docker, name resolution, the router's ports, its admin API and its CA."), width)
	}

	var lines []string

	for _, l := range m.log[start+1:] {
		switch {
		case l.kind == jobs.Failed:
			lines = append(lines, bad.Render(l.text))
		case l.kind != jobs.Output:
			lines = append(lines, muted.Render(l.text))
		case strings.Contains(l.text, "✓"):
			lines = append(lines, ok.Render(l.text))
		case strings.Contains(l.text, "✗"):
			lines = append(lines, bad.Render(l.text))
		default:
			lines = append(lines, l.text)
		}
	}

	return lines
}

// helpView lists every key, in place of the detail view.
func (m Model) helpView(width, height int) string {
	groups := []struct {
		title string
		keys  [][2]string
	}{
		{"moving around", [][2]string{{"1 2 3", "focus a panel"}, {"tab", "next panel"}, {"j k ↑ ↓", "move the selection"}, {"g G", "first, last"}, {"[ ] h l", "previous, next tab"}, {"ctrl+d u", "scroll the view down, up"}, {"/", "filter the checkouts"}}},
		{"selected or marked checkouts", [][2]string{{"u", "up"}, {"d", "down, after asking"}, {"r", "restart"}, {"s", "setup: write .env, then up"}, {"space", "mark for a batch action"}, {"esc", "clear the marks and the filter"}}},
		{"selected checkout", [][2]string{{"o y", "open its URL, copy it"}, {"e E", "shell in its app, open $EDITOR"}, {"x", "remove the worktree"}, {"f", "pause or follow its logs"}}},
		{"project", [][2]string{{"n", "new worktree from a branch"}, {"U D", "start all stopped, stop all"}}},
		{"router", [][2]string{{"3", "routes, doctor, settings"}, {"H", "run rift doctor"}, {"R T", "restart it, trust its CA"}}},
		{"anywhere", [][2]string{{":", "find a command by name"}, {"?", "close this help"}, {"q", "quit"}}},
	}

	inside := width - 4
	columns := max(1, min(2, inside/40))
	blocks := make([][]string, columns)
	total := 0

	for _, g := range groups {
		total += len(g.keys) + 2
	}

	// Groups fill the first column to about half, then the next, so the columns end level.
	column, filled := 0, 0

	for _, g := range groups {
		if column < columns-1 && filled >= (total+columns-1)/columns {
			column, filled = column+1, 0
		}

		block := []string{muted.Render(g.title)}
		for _, k := range g.keys {
			block = append(block, accent.Render(fit(k[0], 10))+k[1])
		}

		blocks[column] = append(blocks[column], append(block, "")...)
		filled += len(g.keys) + 2
	}

	var lines []string

	for i := range len(blocks[0]) {
		line := ""

		for c := range columns {
			cell := ""
			if i < len(blocks[c]) {
				cell = blocks[c][i]
			}

			line += fit(cell, inside/columns)
		}

		lines = append(lines, line)
	}

	return frame(accent.Render("Keys"), "", page(lines, m.scroll, height-2), width, height, true)
}

// modalView draws the open modal as a box to put over the screen.
func (m Model) modalView() string {
	modal := m.modal
	inside := modalWidth - 4

	var lines []string

	for _, l := range modal.lines {
		lines = append(lines, wrap(l, inside)...)
	}

	switch modal.kind {
	case worktreeKind:
		lines = []string{muted.Render("branch  ") + branchFg.Render(modal.input) + accent.Render("▏"), ""}

		switch {
		case modal.input == "":
			lines = append(lines, wrap(muted.Render("An existing branch is checked out; a new one starts from the main checkout."), inside)...)
		case modal.plan == nil:
			lines = append(lines, muted.Render("…"))
		default:
			lines = append(lines,
				fact("domain", accent.Render("https://"+modal.plan.Domain)),
				fact("path", modal.plan.Path),
				fact("compose project", modal.plan.ComposeName),
			)
		}
	case removeKind:
		check := "[ ] "
		if modal.option {
			check = "[x] "
		}

		lines = append(lines, "", accent.Render(check)+"Also delete its Docker volumes, such as its database"+muted.Render("  (v)"))
	case paletteKind:
		lines = []string{accent.Render("› ") + modal.input + accent.Render("▏"), ""}
		found := matching(modal.input)

		for i, c := range found {
			if i == modal.selected {
				lines = append(lines, accent.Bold(true).Render("▸ "+c.label)+muted.Render("  "+c.key))
			} else {
				lines = append(lines, "  "+c.label+muted.Render("  "+c.key))
			}
		}

		if len(found) == 0 {
			lines = append(lines, muted.Render("No command matches."))
		}
	case confirmKind:
	}

	if modal.err != "" {
		lines = append(lines, "")
		lines = append(lines, wrap(bad.Render(modal.err), inside)...)
	}

	return frame(bold.Render(modal.title), "", append(append([]string{""}, lines...), ""), modalWidth, len(lines)+4, modal.kind != removeKind)
}
