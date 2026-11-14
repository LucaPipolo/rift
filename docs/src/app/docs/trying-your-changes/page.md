---
title: Trying your changes
nextjs:
  metadata:
    title: Trying your changes
    description: Build a Rift binary to try a change against a real project, and run rift-dash alone against a stand-in rift.
---

Rift drives the host's Docker, Git, ports and keychain, so most commands only
make sense on your machine, not inside the development container. {% .lead %}

---

## Try a change for real

Build a binary, and run it from any project:

```bash
make build
cd ~/Sites/myapp
~/Sites/rift/builds/rift up
```

{% callout type="warning" title="A built binary touches your machine" %}
`builds/rift` starts and stops real containers, binds ports 80 and 443, and
creates the shared router other projects on your machine depend on. Try it on a
project you can afford to restart.
{% /callout %}

`make rift` is enough for commands that do not touch the host, such as `list` or
`--help`:

```bash
make rift ARGS="up --help"
```

---

## Try the dashboard

`rift dash` needs a real terminal and the host's Docker, so try it with the
built binary too.

To try `rift-dash` alone, without Docker or a project, point it at the stand-in
rift its tests use, which answers with the contract fixture:

```bash
cd dash/internal/rift
RIFT_COMMAND='["sh","testdata/fake-rift"]' ../../../builds/rift-dash
```

After a visible change to the dashboard, run `make demo` to record the demo
again from `docker/demo/dash.tape`. The README and this site both show that
recording.
