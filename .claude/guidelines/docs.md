# Docs

`README.md` is for people using Rift: what it is, shown working in the dashboard demo, installing it, getting
started, everyday commands, configuring `rift.yaml`, and getting help. It is the big picture; how Rift is built
(Laravel Zero, the static PHP runtime, rift-dash in Go) belongs in `CONTRIBUTING.md`, which holds everything someone
working on Rift itself needs: the stack, the development commands, trying a change, the project structure, code conventions, tests, commit messages, pull
requests, AI tooling (commit trailers, the hand-invoked skills, the MCP servers configured for this project),
releasing, and reporting bugs.

Keep both true to the current state of the repo in the same task that changes it, in two directions: update them
when a change makes something they already say inaccurate (a command, an option, a `rift.yaml` key, a setup step, a
make target, a behaviour they describe), and add to them when a change introduces something they should cover but
don't yet (a new command or option, a new `rift.yaml` key, a new make target, a new human-invocable skill, a new
project-specific MCP server, a change to the release process or the project structure). Silence is only correct when
nothing in that list actually moved. When the dashboard looks different, record the demo again with `make demo`.

A docs change ships in its own `docs:` commit, after the code it describes, unless it only makes sense together with
that code, such as a renamed option.
