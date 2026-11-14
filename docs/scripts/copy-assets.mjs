// Copy the dashboard demo from the repository into public/, so the site and
// README.md always show the same recording, the one `make demo` writes.
import { copyFileSync, mkdirSync } from 'node:fs'

mkdirSync('public', { recursive: true })
copyFileSync('../.github/dash.gif', 'public/dash.gif')
