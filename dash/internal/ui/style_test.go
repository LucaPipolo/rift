package ui

import (
	"testing"

	"github.com/LucaPipolo/rift/dash/internal/snapshot"
)

// TestEveryStateHasAGlyph makes sure a state added to the contract is never drawn blank.
func TestEveryStateHasAGlyph(t *testing.T) {
	for _, state := range snapshot.States {
		if glyph(state) == "" {
			t.Errorf("%s has none", state)
		}
	}
}
