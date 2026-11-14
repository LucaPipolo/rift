package ui

import (
	"strings"

	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// Panel is a panel of the dashboard that can hold the keyboard focus.
type Panel int

// The panels, numbered as their keys are.
const (
	Projects Panel = iota + 1
	Checkouts
	Router
)

const panels = int(Router)

// Tab is a tab of the selected checkout's detail view.
type Tab int

// The detail tabs, in the order of tabNames.
const (
	Overview Tab = iota
	Containers
	Logs
	Env
	Hooks
	Git
)

var tabNames = []string{"Overview", "Containers", "Logs", "Env", "Hooks", "Git"}

// RouterTab is a tab of the router view, shown while the router panel has the focus.
type RouterTab int

// The router tabs, in the order of routerTabNames.
const (
	Routes RouterTab = iota
	Doctor
	Config
)

var routerTabNames = []string{"Routes", "Doctor", "Config"}

// cycle steps a value through 0 to n-1, wrapping around at either end.
func cycle(value, step, n int) int {
	return ((value+step)%n + n) % n
}

// selectedProject returns the selected project, falling back to the first.
func (m Model) selectedProject() *snapshot.Project {
	for i := range m.snap.Projects {
		if m.snap.Projects[i].Root == m.project {
			return &m.snap.Projects[i]
		}
	}

	if len(m.snap.Projects) > 0 {
		return &m.snap.Projects[0]
	}

	return nil
}

// visible returns the selected project's checkouts that match the filter.
func (m Model) visible() []snapshot.Checkout {
	project := m.selectedProject()
	if project == nil {
		return nil
	}

	if m.filter == "" {
		return project.Checkouts
	}

	var shown []snapshot.Checkout

	for _, c := range project.Checkouts {
		branch := ""
		if c.Branch != nil {
			branch = *c.Branch
		}

		if strings.Contains(strings.ToLower(c.Label+" "+branch), strings.ToLower(m.filter)) {
			shown = append(shown, c)
		}
	}

	return shown
}

// selectedCheckout returns the selected checkout among those shown, falling back to the first.
func (m Model) selectedCheckout() *snapshot.Checkout {
	shown := m.visible()

	for i := range shown {
		if shown[i].Root == m.checkout {
			return &shown[i]
		}
	}

	if len(shown) > 0 {
		return &shown[0]
	}

	return nil
}

// move moves the selection in the focused list, stopping at either end.
func (m *Model) move(step int) {
	switch m.focus {
	case Projects:
		roots := make([]string, 0, len(m.snap.Projects))
		for _, p := range m.snap.Projects {
			roots = append(roots, p.Root)
		}

		current := ""
		if p := m.selectedProject(); p != nil {
			current = p.Root
		}

		m.project = stepTo(roots, current, step)
		m.checkout = ""
		m.marked = map[string]bool{}
		m.filter = ""
	case Checkouts:
		shown := m.visible()
		roots := make([]string, 0, len(shown))

		for _, c := range shown {
			roots = append(roots, c.Root)
		}

		current := ""
		if c := m.selectedCheckout(); c != nil {
			current = c.Root
		}

		m.checkout = stepTo(roots, current, step)
	case Router:
	}
}

// stepTo steps through a list of roots from the current one, stopping at either end.
func stepTo(roots []string, current string, step int) string {
	if len(roots) == 0 {
		return ""
	}

	index := 0

	for i, root := range roots {
		if root == current {
			index = i
		}
	}

	return roots[max(0, min(len(roots)-1, index+step))]
}

// targets returns the checkouts an action applies to: the marked ones, or else the selected one.
func (m Model) targets() []snapshot.Checkout {
	var marked []snapshot.Checkout

	for _, c := range m.visible() {
		if m.marked[c.Root] {
			marked = append(marked, c)
		}
	}

	if len(marked) > 0 {
		return marked
	}

	if c := m.selectedCheckout(); c != nil {
		return []snapshot.Checkout{*c}
	}

	return nil
}
