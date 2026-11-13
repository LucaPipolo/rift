package ui

import (
	"reflect"
	"testing"
)

// TestMovesBetweenProjectsAndForgetsTheirSelection makes sure marks and a filter never carry over to another project.
func TestMovesBetweenProjectsAndForgetsTheirSelection(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "space", "1", "j")

	if m.project != "/Sites/shop" || m.checkout != "" || len(m.marked) != 0 {
		t.Fatalf("got %s %s %v", m.project, m.checkout, m.marked)
	}

	m, _ = press(m, "enter")
	if m.focus != Checkouts {
		t.Fatalf("got %v", m.focus)
	}
}

// TestMovesNothingWhileTheRouterHasTheFocus covers keys that move a selection, with no list to move in.
func TestMovesNothingWhileTheRouterHasTheFocus(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "3", "j", "enter")

	if m.checkout != "/Sites/myapp-search" || m.focus != Router {
		t.Fatalf("got %s %v", m.checkout, m.focus)
	}
}

// TestStepThroughAnEmptyListSelectsNothing covers moving in a project without checkouts.
func TestStepThroughAnEmptyListSelectsNothing(t *testing.T) {
	if stepTo(nil, "", 1) != "" {
		t.Fatal("selected from an empty list")
	}
}

// TestFilterMatchesBranches covers a filter that matches a branch but no checkout name.
func TestFilterMatchesBranches(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "/", "r", "e", "f", "a")

	if got := m.visible(); len(got) != 1 || got[0].Label != "orders" {
		t.Fatalf("got %v", got)
	}

	if m, _ = press(m, "esc"); m.filtering || m.filter != "" {
		t.Fatal("esc kept filtering")
	}
}

// TestFilterThatMatchesNothing makes sure no action targets a checkout the filter hides.
func TestFilterThatMatchesNothing(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "/", "z", "z")

	contains(t, screen(m), "No checkout matches “zz”.", "Nothing selected.")

	if m.targets() != nil {
		t.Fatal("targeted a hidden checkout")
	}
}

// TestMarkedCheckoutsAreTheTargets also covers unmarking a checkout with a second press.
func TestMarkedCheckoutsAreTheTargets(t *testing.T) {
	m, _ := dashboard(t)
	m, _ = press(m, "space", "space")

	got := []string{}
	for _, c := range m.targets() {
		got = append(got, c.Label)
	}

	if !reflect.DeepEqual(got, []string{"search", "pdf-export"}) {
		t.Fatalf("got %v", got)
	}

	if m, _ = press(m, "k", "space"); len(m.marked) != 1 {
		t.Fatal("a second press did not unmark")
	}
}
