package ui

import (
	"strings"
	"time"

	tea "charm.land/bubbletea/v2"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// modalKind is what a modal asks.
type modalKind int

const (
	confirmKind modalKind = iota
	worktreeKind
	removeKind
	paletteKind
)

// planDelay is how long after the last key rift is asked where a worktree would go,
// so typing a branch does not start rift once per letter.
const planDelay = 150 * time.Millisecond

// Modal is a question the dashboard asks before acting.
type Modal struct {
	kind     modalKind
	title    string
	lines    []string
	action   string
	confirm  func(Model, *Modal) (Model, tea.Cmd)
	input    string
	selected int
	// option is whether a removed worktree's volumes are deleted too.
	option   bool
	plan     *snapshot.Plan
	err      string
	project  snapshot.Project
	checkout snapshot.Checkout
}

// command is one entry of the command palette.
type command struct {
	key   string
	label string
}

var commands = []command{
	{"u", "Up: start the selected or marked checkouts"},
	{"s", "Setup: write .env, then start them"},
	{"r", "Restart the selected or marked checkouts"},
	{"d", "Down: stop the selected or marked checkouts"},
	{"U", "Start every stopped checkout of the project"},
	{"D", "Stop every checkout of the project"},
	{"n", "New worktree from a branch"},
	{"x", "Remove the selected worktree"},
	{"o", "Open the selected checkout in the browser"},
	{"y", "Copy the selected checkout's URL"},
	{"e", "Shell into the selected checkout's app container"},
	{"E", "Open the selected checkout in $EDITOR"},
	{"/", "Filter the checkouts by name or branch"},
	{"H", "Doctor: check this machine's rift setup"},
	{"R", "Restart the router"},
	{"T", "Trust the router's CA"},
	{"?", "Show every key"},
	{"q", "Quit"},
}

// confirmModal asks before doing something.
func confirmModal(title string, lines []string, action string, confirm func(Model, *Modal) (Model, tea.Cmd)) *Modal {
	return &Modal{kind: confirmKind, title: title, lines: lines, action: action, confirm: confirm}
}

// paletteModal finds a command by typing part of its name.
func paletteModal() *Modal {
	return &Modal{kind: paletteKind, title: "Commands", action: "run"}
}

// matching returns the palette's commands that match what has been typed.
func matching(filter string) []command {
	var found []command

	for _, c := range commands {
		if strings.Contains(strings.ToLower(c.label), strings.ToLower(strings.TrimSpace(filter))) {
			found = append(found, c)
		}
	}

	return found
}

// answer handles a key while a modal is open.
func (m Model) answer(key string) (tea.Model, tea.Cmd) {
	modal := m.modal

	if key == "esc" {
		m.modal = nil

		return m, nil
	}

	switch modal.kind {
	case paletteKind:
		return m.palette(key)
	case worktreeKind:
		return m.typeBranch(key)
	case removeKind:
		if key == "v" {
			modal.option = !modal.option

			return m, nil
		}
	case confirmKind:
	}

	switch key {
	case "n", "q":
		m.modal = nil
	case "enter", "y":
		m.modal = nil

		return modal.confirm(m, modal)
	}

	return m, nil
}

// palette types into the command palette, moves through it, or runs the highlighted command.
func (m Model) palette(key string) (tea.Model, tea.Cmd) {
	modal := m.modal
	found := matching(modal.input)

	switch key {
	case "down", "tab":
		modal.selected = max(0, min(len(found)-1, modal.selected+1))
	case "up", "shift+tab":
		modal.selected = max(0, modal.selected-1)
	case "backspace":
		modal.input, modal.selected = dropLast(modal.input), 0
	case "space":
		modal.input, modal.selected = modal.input+" ", 0
	case "enter":
		if modal.selected < len(found) {
			m.modal = nil

			return m.key(found[modal.selected].key)
		}
	default:
		if len([]rune(key)) == 1 {
			modal.input, modal.selected = modal.input+key, 0
		}
	}

	return m, nil
}

// typeBranch types the branch of a new worktree, asking rift where it would go once the typing pauses.
func (m Model) typeBranch(key string) (tea.Model, tea.Cmd) {
	modal := m.modal

	switch key {
	case "enter":
		return m, m.plan(modal.input, true)
	case "backspace":
		modal.input = dropLast(modal.input)
	case "space":
		return m, nil
	default:
		if len([]rune(key)) != 1 {
			return m, nil
		}

		modal.input += key
	}

	modal.plan, modal.err = nil, ""

	if modal.input == "" {
		return m, nil
	}

	input := modal.input

	return m, tea.Tick(planDelay, func(time.Time) tea.Msg { return planDueMsg{input: input} })
}

// planDue asks rift where the worktree would go, unless the branch has changed since, which asks again later.
func (m Model) planDue(msg planDueMsg) (tea.Model, tea.Cmd) {
	if m.modal == nil || m.modal.kind != worktreeKind || msg.input != m.modal.input {
		return m, nil
	}

	return m, m.plan(msg.input, false)
}

// plan asks rift where a worktree for the typed branch would go.
func (m Model) plan(input string, submit bool) tea.Cmd {
	project := m.modal.project.Root

	return func() tea.Msg {
		plan, err := m.backend.Plan(project, input)

		return planMsg{input: input, submit: submit, plan: plan, err: err}
	}
}

// planned shows where the worktree would go, and creates it once the person has asked for it.
func (m Model) planned(msg planMsg) (tea.Model, tea.Cmd) {
	modal := m.modal

	if modal == nil || modal.kind != worktreeKind || msg.input != modal.input {
		return m, nil
	}

	if msg.err != nil {
		modal.err = msg.err.Error()

		return m, nil
	}

	modal.plan = &msg.plan

	if !msg.submit {
		return m, nil
	}

	if msg.plan.Error != nil {
		modal.err = *msg.plan.Error

		return m, nil
	}

	add := []string{"git", "worktree", "add", "-b", msg.plan.Branch, msg.plan.Path}
	if msg.plan.Exists {
		add = []string{"git", "worktree", "add", msg.plan.Path, msg.plan.Branch}
	}

	m.modal = nil
	m.checkout = msg.plan.Path
	m.backend.Run(
		jobs.Task{Label: "git worktree add · " + msg.plan.Branch, Dir: modal.project.Root, Command: add},
		jobs.Task{Label: "rift setup · " + msg.plan.Domain, Dir: msg.plan.Path, Command: m.backend.Rift.Command("setup")},
	)

	return m, nil
}
