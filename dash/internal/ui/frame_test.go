package ui

import (
	"testing"
)

// TestFitIntoNoWidthDrawsNothing covers a column squeezed to nothing on a small terminal.
func TestFitIntoNoWidthDrawsNothing(t *testing.T) {
	if fit("abc", 0) != "" {
		t.Fatal("drew into no width")
	}
}

// TestFrameTooSmallDrawsNothing covers a frame with no room inside its border.
func TestFrameTooSmallDrawsNothing(t *testing.T) {
	if frame("x", "", nil, 3, 5, false) != "" || frame("x", "", nil, 10, 1, false) != "" {
		t.Fatal("drew a frame with no inside")
	}
}
