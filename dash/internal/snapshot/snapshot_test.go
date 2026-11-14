package snapshot

import (
	"bytes"
	"encoding/json"
	"os"
	"reflect"
	"strings"
	"testing"
)

// fixture reads the contract fixture Rift's own tests produce exactly.
func fixture(t *testing.T) Snapshot {
	t.Helper()

	data, err := os.ReadFile("../../testdata/snapshot.json")
	if err != nil {
		t.Fatal(err)
	}

	s, err := Decode(data)
	if err != nil {
		t.Fatal(err)
	}

	return s
}

// TestDecodeReadsTheContractFixture spot-checks fields of every kind, nullable ones included, against the fixture
// Rift's tests produce.
func TestDecodeReadsTheContractFixture(t *testing.T) {
	s := fixture(t)

	if s.Router.Name != "rift-router" || !s.Router.Trusted || len(s.Router.Routes) != 3 || s.Router.Routes[1].Reachable {
		t.Fatalf("router: %+v", s.Router)
	}

	if len(s.Projects) != 3 || s.Projects[0].Name != "myapp" || *s.Projects[2].Error == "" {
		t.Fatalf("projects: %+v", s.Projects)
	}

	search := s.Projects[0].Checkouts[1]
	if search.Label != "search" || *search.Branch != "feat/search" || search.State != Serving || search.Git.Ahead != 3 || *search.Git.ChangedAt == 0 {
		t.Fatalf("search: %+v", search)
	}

	shop := s.Projects[1].Checkouts[0]
	if shop.Preset != "sail" || len(shop.Hooks) != 3 || shop.Env[2].Value != nil || *shop.Ports[0].Value != "3307" {
		t.Fatalf("shop: %+v", shop)
	}
}

// TestDecodeRefusesAnotherVersion covers rift and rift-dash from different releases.
func TestDecodeRefusesAnotherVersion(t *testing.T) {
	_, err := Decode([]byte(`{"version":2}`))

	if err == nil || !strings.Contains(err.Error(), "rift sent snapshot version 2, but this rift-dash reads version 1") {
		t.Fatalf("got %v", err)
	}
}

// TestDecodeRefusesWhatIsNotJSON covers a line that is not a snapshot at all.
func TestDecodeRefusesWhatIsNotJSON(t *testing.T) {
	_, err := Decode([]byte(`not json`))

	if err == nil || !strings.HasPrefix(err.Error(), "rift sent a snapshot rift-dash cannot read") {
		t.Fatalf("got %v", err)
	}
}

// TestDecodePlanReadsAWorktreePlan reads a plan as `rift dash:plan` prints it.
func TestDecodePlanReadsAWorktreePlan(t *testing.T) {
	p, err := DecodePlan([]byte(`{"branch":"feat/x","path":"/Sites/myapp-feat-x","domain":"feat-x.myapp.test","composeName":"myapp-feat-x","exists":true,"error":null}`))

	if err != nil || p.Domain != "feat-x.myapp.test" || !p.Exists || p.Error != nil {
		t.Fatalf("got %+v, %v", p, err)
	}
}

// TestDecodePlanRefusesWhatIsNotJSON covers rift printing something other than a plan.
func TestDecodePlanRefusesWhatIsNotJSON(t *testing.T) {
	if _, err := DecodePlan([]byte(`nope`)); err == nil {
		t.Fatal("expected an error")
	}
}

// TestIsUpOnlyWhileTheAppContainerRuns covers every state, since a failed checkout has an app container that is not
// running.
func TestIsUpOnlyWhileTheAppContainerRuns(t *testing.T) {
	for state, up := range map[State]bool{Serving: true, Unrouted: true, Unreachable: true, Failed: false, Stopped: false} {
		if state.IsUp() != up {
			t.Errorf("%s: got %v", state, !up)
		}
	}
}

// TestCountCountsCheckoutsInAState counts across every project, as the top bar does.
func TestCountCountsCheckoutsInAState(t *testing.T) {
	s := fixture(t)

	if s.Count(Serving) != 3 || s.Count(Failed) != 1 || s.Count(Unrouted) != 0 {
		t.Fatalf("counts: %d %d %d", s.Count(Serving), s.Count(Failed), s.Count(Unrouted))
	}
}

// TestUpCountCountsRunningCheckouts counts one project's running checkouts, as the projects panel shows them.
func TestUpCountCountsRunningCheckouts(t *testing.T) {
	if got := fixture(t).Projects[0].UpCount(); got != 3 {
		t.Fatalf("got %d", got)
	}
}

// TestAppFindsTheContainerTheRouterServes covers a checkout with an app container and one without any container.
func TestAppFindsTheContainerTheRouterServes(t *testing.T) {
	s := fixture(t)

	if app := s.Projects[0].Checkouts[3].App(); app == nil || *app.ExitCode != 137 {
		t.Fatalf("got %+v", app)
	}

	if app := s.Projects[0].Checkouts[4].App(); app != nil {
		t.Fatalf("got %+v", app)
	}
}

// TestTheContractFixtureRoundTripsExactly checks the contract both ways: every field the fixture holds is one
// rift-dash reads, and nothing it reads is missing, so a field Rift adds or renames fails here until rift-dash knows
// it.
func TestTheContractFixtureRoundTripsExactly(t *testing.T) {
	data, err := os.ReadFile("../../testdata/snapshot.json")
	if err != nil {
		t.Fatal(err)
	}

	decoder := json.NewDecoder(bytes.NewReader(data))
	decoder.DisallowUnknownFields()

	var s Snapshot
	if err := decoder.Decode(&s); err != nil {
		t.Fatal(err)
	}

	encoded, err := json.Marshal(s)
	if err != nil {
		t.Fatal(err)
	}

	var want, got any

	_ = json.Unmarshal(data, &want)
	_ = json.Unmarshal(encoded, &got)

	if !reflect.DeepEqual(want, got) {
		t.Fatalf("the fixture and what rift-dash reads differ:\nfixture: %s\nread:    %s", data, encoded)
	}
}
