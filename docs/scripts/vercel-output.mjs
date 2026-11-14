// Turns the static export in out/ into Vercel's Build Output (.vercel/output),
// so every page is served with a Content Security Policy listing the hashes of
// its own inline scripts.
//
// We decided on a policy per page, generated here, because Next.js inlines a
// different set of scripts into every page: one site-wide policy would carry
// every hash of every page and go stale with each content change.
// @see https://vercel.com/docs/build-output-api/configuration
import { createHash } from 'node:crypto'
import {
  cpSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from 'node:fs'
import { join, relative } from 'node:path'

const OUT = 'out'
const OUTPUT = '.vercel/output'
const STATIC = join(OUTPUT, 'static')

const INLINE_SCRIPT = /<script(\s[^>]*)?>([\s\S]*?)<\/script>/g
// A data block is never run, so script-src does not govern it and it needs no hash.
const DATA_BLOCK = /\stype="application\/(?:ld\+)?json"/
const INLINE_STYLE = /<style[\s>]|\sstyle="/

const HEADERS = {
  'Strict-Transport-Security': 'max-age=63072000; includeSubDomains',
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Permissions-Policy':
    'accelerometer=(), autoplay=(), browsing-topics=(), camera=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), serial=(), usb=(), xr-spatial-tracking=()',
  'Cross-Origin-Opener-Policy': 'same-origin',
  'Cross-Origin-Resource-Policy': 'same-origin',
  'X-Permitted-Cross-Domain-Policies': 'none',
  'X-DNS-Prefetch-Control': 'off',
}

/**
 * Lists every file under a directory.
 *
 * @param {string} directory Where to start.
 * @returns {string[]} The paths of the files, relative to the working directory.
 */
function files(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    let path = join(directory, entry.name)
    return entry.isDirectory() ? files(path) : [path]
  })
}

/**
 * Builds the Content Security Policy for one page.
 *
 * @param {string} html The page as built.
 * @param {string} page Its path, for the error message.
 * @returns {string} The policy, allowing exactly the inline scripts the page holds.
 */
function policy(html, page) {
  if (INLINE_STYLE.test(html)) {
    throw new Error(
      `${page} carries an inline style, which style-src 'self' blocks. ` +
        'Move it into a class, or render the element only on the client.',
    )
  }

  let hashes = new Set()

  for (let [, attributes = '', body] of html.matchAll(INLINE_SCRIPT)) {
    if (/\ssrc=/.test(attributes) || DATA_BLOCK.test(attributes) || !body) {
      continue
    }
    hashes.add(`'sha256-${createHash('sha256').update(body).digest('base64')}'`)
  }

  return [
    "default-src 'none'",
    `script-src 'self' ${[...hashes].join(' ')}`.trim(),
    "style-src 'self'",
    "img-src 'self' data:",
    "font-src 'self'",
    "connect-src 'self'",
    "manifest-src 'self'",
    "object-src 'none'",
    "base-uri 'none'",
    "form-action 'none'",
    "frame-ancestors 'none'",
    'upgrade-insecure-requests',
  ].join('; ')
}

/**
 * Escapes a path for use inside a route's regular expression.
 *
 * @param {string} path A URL path.
 * @returns {string} The path with regex metacharacters escaped.
 */
function escape(path) {
  return path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

rmSync(OUTPUT, { recursive: true, force: true })
mkdirSync(STATIC, { recursive: true })
cpSync(OUT, STATIC, { recursive: true })

// Next.js writes the not-found page twice. 404.html is the one served, so the
// other copy is removed rather than left reachable without a policy.
rmSync(join(STATIC, '_not-found.html'), { force: true })

let overrides = {}
let pageRoutes = []
let notFound = null

for (let file of files(STATIC).filter((path) => path.endsWith('.html'))) {
  let name = relative(STATIC, file)
  let html = readFileSync(file, 'utf8')

  let csp = policy(html, name)
  let paths = [name]

  if (name === '404.html') {
    notFound = csp
  } else {
    let path = name === 'index.html' ? '' : name.replace(/\.html$/, '')
    if (path) overrides[name] = { path }
    paths.unshift(path)
  }

  // A page answers at its clean path and, where the filesystem still serves
  // it, at its file name: both must carry its policy.
  pageRoutes.push({
    src: `^/(?:${paths.map(escape).join('|')})$`,
    headers: { 'Content-Security-Policy': csp },
    continue: true,
  })
}

if (!notFound) throw new Error(`${OUT}/404.html is missing.`)

let config = {
  version: 3,
  overrides,
  routes: [
    // A path starting with a second slash or a backslash is left alone:
    // redirecting it would produce a protocol-relative URL to another host.
    { src: '^/([^/\\\\].*)/$', status: 308, headers: { Location: '/$1' } },
    { src: '^/(.*)$', headers: HEADERS, continue: true },
    {
      src: '^/_next/static/(.*)$',
      headers: { 'Cache-Control': 'public, max-age=31536000, immutable' },
      continue: true,
    },
    ...pageRoutes,
    { handle: 'filesystem' },
    {
      src: '^/(?!_vercel/).*$',
      status: 404,
      dest: '/404.html',
      headers: { 'Content-Security-Policy': notFound },
    },
  ],
}

writeFileSync(join(OUTPUT, 'config.json'), JSON.stringify(config, null, 2))
console.log(
  `Wrote ${OUTPUT} with a Content Security Policy for ${pageRoutes.length} pages.`,
)
