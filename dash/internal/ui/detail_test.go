package ui

import (
	"strings"
	"testing"

	"github.com/LucaPipolo/rift/dash/internal/jobs"
	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// text returns a pointer to a string, as the contract uses for values that may be missing.
func text(s string) *string { return &s }

// odd returns the dashboard on a checkout in each state the fixture leaves out, and a router that is not well.
func odd(t *testing.T) Model {
	t.Helper()

	m, _ := dashboard(t)
	search := &m.snap.Projects[0].Checkouts[1]
	search.State = snapshot.Unrouted
	search.Ports[0].Value = text("5173")
	search.Git = snapshot.Git{}
	search.Containers = append(search.Containers,
		snapshot.Container{Service: "db", State: "running", Status: "Up 1 second (unhealthy)", Running: true, Health: text("unhealthy")},
		snapshot.Container{Service: "cache", State: "running", Status: "Up 1 second (health: starting)", Running: true, Health: text("starting")},
		snapshot.Container{Service: "worker", State: "created", Status: "Created"},
	)
	m.snap.Router.Trusted = false

	return m
}

// TestAnUnroutedCheckoutSaysHowToRouteIt also covers a port moved off its default, a branch level with main, and no
// uncommitted files.
func TestAnUnroutedCheckoutSaysHowToRouteIt(t *testing.T) {
	got := screen(odd(t))

	contains(t, got, "Running, but the router has no route for it. Press u to route it.", "◌ running, not routed", "– https://search.myapp.test", "! CA not trusted")
	contains(t, got, "VITE_PORT         5173", "level with main", "uncommitted       nothing")
}

// TestContainersShowEveryHealth covers the health states and the container state the fixture leaves out.
func TestContainersShowEveryHealth(t *testing.T) {
	m, _ := press(odd(t), "]")

	contains(t, screen(m), "unhealthy", "starting", "created")
}

// TestACheckoutWithoutContainersSaysHowToCreateThem covers a checkout that was never started.
func TestACheckoutWithoutContainersSaysHowToCreateThem(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "G", "]")

	contains(t, screen(m), "No containers for myapp-fix-login. Press u to create them.")
}

// TestACleanCheckoutsGitTabSaysSo covers a checkout with no uncommitted files.
func TestACleanCheckoutsGitTabSaysSo(t *testing.T) {
	m, _ := press(odd(t), "[")

	contains(t, screen(m), "uncommitted (0)", "  clean")
}

// TestACheckoutWithNoCommitsSaysNone makes sure an empty list reads as none, not as still reading.
func TestACheckoutWithNoCommitsSaysNone(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "[")
	next, _ := m.Update(commitsMsg{dir: m.commitsFor, commits: []Commit{}})

	contains(t, screen(next.(Model)), "  none")
}

// TestAStoppedRouterSaysHowToStartIt covers the router panel and the routes tab before any checkout has started it.
func TestAStoppedRouterSaysHowToStartIt(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Router.Running = false
	m, _ = press(m, "3")

	contains(t, screen(m), "○ stopped", "Start a checkout to start it.", "The router is not running.")
}

// TestARouterWithNothingRegisteredSaysSo covers a running router with no routes.
func TestARouterWithNothingRegisteredSaysSo(t *testing.T) {
	m, _ := dashboard(t)
	m.snap.Router.Routes = nil
	m, _ = press(m, "3")

	contains(t, screen(m), "Nothing registered.", "0 routes")
}

// TestARouterWhoseCAIsNotTrustedSaysHowToTrustIt covers the router's config tab.
func TestARouterWhoseCAIsNotTrustedSaysHowToTrustIt(t *testing.T) {
	m, _ := press(odd(t), "3", "[")

	contains(t, screen(m), "! not trusted: press T")
}

// TestTheDoctorTabShowsOtherTasksDimmed covers a task started after doctor, whose lines are not part of its report.
func TestTheDoctorTabShowsOtherTasksDimmed(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "3", "]")

	for _, e := range []jobs.Event{
		{Kind: jobs.Started, Text: "rift doctor"},
		{Kind: jobs.Started, Text: "rift up · shop.test"},
	} {
		next, _ := m.Update(jobMsg(e))
		m = next.(Model)
	}

	contains(t, screen(m), "rift up · shop.test")
}

// TestDoctorTabShowsTheLatestReport makes sure an older report is not mixed into the latest one.
func TestDoctorTabShowsTheLatestReport(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "3", "]")
	contains(t, screen(m), "Press H to check this machine")

	for _, e := range []jobs.Event{
		{Kind: jobs.Started, Text: "rift doctor"},
		{Kind: jobs.Output, Text: "an old report"},
		{Kind: jobs.Done, Text: "rift doctor · done in 1s"},
		{Kind: jobs.Started, Text: "rift doctor"},
		{Kind: jobs.Output, Text: "✓ Docker is running"},
		{Kind: jobs.Output, Text: "✗ App container is on the router network"},
		{Kind: jobs.Output, Text: "machine"},
		{Kind: jobs.Failed, Text: "rift doctor · failed after 2s"},
	} {
		next, _ := m.Update(jobMsg(e))
		m = next.(Model)
	}

	got := screen(m)
	contains(t, got, "✓ Docker is running", "✗ App container is on the router network", "machine", "rift doctor · failed after 2s")

	if strings.Contains(got, "an old report") {
		t.Fatal("showed an older report")
	}
}

// TestHelpScrollsOnASmallTerminal makes sure every key can be read at the smallest size.
func TestHelpScrollsOnASmallTerminal(t *testing.T) {
	m, _ := loaded(t, "/Sites/myapp-search", MinWidth, MinHeight)
	m, _ = press(m, "?")
	contains(t, screen(m), "more · ctrl+d")

	for range 10 {
		m, _ = press(m, "ctrl+d")
	}

	contains(t, screen(m), "quit")
}

// TestANewWorktreeExplainsBranchesBeforeAnythingIsTyped covers the empty modal, which explains what a branch name
// does.
func TestANewWorktreeExplainsBranchesBeforeAnythingIsTyped(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "n")

	contains(t, screen(m), "An existing branch is checked out; a new one starts from the main")
}

// TestANewWorktreeWaitsForItsPlan covers the moment between typing and rift answering.
func TestANewWorktreeWaitsForItsPlan(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "n", "a")

	contains(t, screen(m), "branch  a▏", "…")
}
