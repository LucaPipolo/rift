// Package snapshot holds what `rift dash:watch` reports about the machine, as Rift's JSON contract describes it.
//
// Rift works out everything here, down to domains and where each .env value
// comes from, so rift-dash never repeats one of Rift's rules.
package snapshot

import (
	"encoding/json"
	"fmt"
)

// Version is the contract version this rift-dash reads.
const Version = 1

// Snapshot is everything the dashboard shows at one moment.
type Snapshot struct {
	Version  int       `json:"version"`
	Router   Router    `json:"router"`
	Projects []Project `json:"projects"`
}

// Router is the shared Caddy router every project routes through.
type Router struct {
	Name       string   `json:"name"`
	Image      string   `json:"image"`
	Admin      string   `json:"admin"`
	Listening  string   `json:"listening"`
	Binding    string   `json:"binding"`
	Network    string   `json:"network"`
	ConfigPath string   `json:"configPath"`
	Running    bool     `json:"running"`
	Answering  bool     `json:"answering"`
	Trusted    bool     `json:"trusted"`
	Members    []string `json:"members"`
	Routes     []Route  `json:"routes"`
}

// Route is one route the router has registered.
type Route struct {
	ID        string   `json:"id"`
	Hosts     []string `json:"hosts"`
	Upstream  string   `json:"upstream"`
	Reachable bool     `json:"reachable"`
}

// Project is one project Rift has started, with each of its checkouts.
type Project struct {
	Name      string     `json:"name"`
	Root      string     `json:"root"`
	Domain    *string    `json:"domain"`
	Error     *string    `json:"error"`
	Checkouts []Checkout `json:"checkouts"`
}

// Checkout is the main clone or one worktree of a project.
type Checkout struct {
	Label       string      `json:"label"`
	Root        string      `json:"root"`
	Main        bool        `json:"main"`
	Branch      *string     `json:"branch"`
	State       State       `json:"state"`
	Up          bool        `json:"up"`
	Domain      string      `json:"domain"`
	URLs        []string    `json:"urls"`
	ComposeName string      `json:"composeName"`
	Preset      string      `json:"preset"`
	AppService  string      `json:"appService"`
	AppPort     int         `json:"appPort"`
	ExitCode    *int        `json:"exitCode"`
	Network     string      `json:"network"`
	Containers  []Container `json:"containers"`
	Git         Git         `json:"git"`
	Ports       []Port      `json:"ports"`
	Env         []EnvValue  `json:"env"`
	Hooks       []Hook      `json:"hooks"`
	Shell       []string    `json:"shell"`
	Logs        []string    `json:"logs"`
}

// State is how far a checkout is from serving at its own domain.
type State string

const (
	Serving     State = "serving"
	Unrouted    State = "unrouted"
	Unreachable State = "unreachable"
	Failed      State = "failed"
	Stopped     State = "stopped"
)

// States lists every state, in the order the top bar counts them.
var States = []State{Serving, Unrouted, Unreachable, Failed, Stopped}

// Container is one container of a checkout's Compose project.
type Container struct {
	Name     string  `json:"name"`
	Service  string  `json:"service"`
	State    string  `json:"state"`
	Status   string  `json:"status"`
	Running  bool    `json:"running"`
	ExitCode *int    `json:"exitCode"`
	Health   *string `json:"health"`
}

// Git is where a checkout's branch stands against the main branch, and what it has not committed.
type Git struct {
	Ahead     int      `json:"ahead"`
	Behind    int      `json:"behind"`
	Changes   []string `json:"changes"`
	ChangedAt *int64   `json:"changedAt"`
}

// Port is a host port Rift picked for a checkout.
type Port struct {
	Key     string  `json:"key"`
	Value   *string `json:"value"`
	Default int     `json:"default"`
}

// EnvValue is one .env value Rift manages, and where it comes from.
type EnvValue struct {
	Key    string  `json:"key"`
	Value  *string `json:"value"`
	Source string  `json:"source"`
}

// Hook is one command a checkout runs around `rift up` and `rift down`.
type Hook struct {
	Stage        string  `json:"stage"`
	Label        *string `json:"label"`
	Run          string  `json:"run"`
	OnHost       bool    `json:"onHost"`
	UnlessExists *string `json:"unlessExists"`
}

// Plan is where a new worktree would go, or why it cannot be made, as `rift dash:plan` reports it.
type Plan struct {
	Branch      string `json:"branch"`
	Path        string `json:"path"`
	Domain      string `json:"domain"`
	ComposeName string `json:"composeName"`
	// Exists is whether the branch is already there to check out, rather than one to create.
	Exists bool    `json:"exists"`
	Error  *string `json:"error"`
}

// Decode reads one snapshot, refusing a contract version this rift-dash would misread.
func Decode(data []byte) (Snapshot, error) {
	var s Snapshot

	if err := json.Unmarshal(data, &s); err != nil {
		return Snapshot{}, fmt.Errorf("rift sent a snapshot rift-dash cannot read: %w", err)
	}

	if s.Version != Version {
		return Snapshot{}, fmt.Errorf("rift sent snapshot version %d, but this rift-dash reads version %d. Install rift and rift-dash from the same release", s.Version, Version)
	}

	return s, nil
}

// DecodePlan reads a worktree plan.
func DecodePlan(data []byte) (Plan, error) {
	var p Plan

	if err := json.Unmarshal(data, &p); err != nil {
		return Plan{}, fmt.Errorf("rift sent a worktree plan rift-dash cannot read: %w", err)
	}

	return p, nil
}

// IsUp reports whether a checkout's app container is running.
func (s State) IsUp() bool {
	return s == Serving || s == Unrouted || s == Unreachable
}

// Count counts every project's checkouts in a state.
func (s Snapshot) Count(state State) int {
	count := 0

	for _, p := range s.Projects {
		for _, c := range p.Checkouts {
			if c.State == state {
				count++
			}
		}
	}

	return count
}

// UpCount counts a project's checkouts whose app container is running.
func (p Project) UpCount() int {
	count := 0

	for _, c := range p.Checkouts {
		if c.Up {
			count++
		}
	}

	return count
}

// App returns the container the router serves a checkout from, if it exists.
func (c Checkout) App() *Container {
	for i := range c.Containers {
		if c.Containers[i].Service == c.AppService {
			return &c.Containers[i]
		}
	}

	return nil
}
