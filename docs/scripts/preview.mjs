// Serves .vercel/output as Vercel would, headers and all, so the Content
// Security Policy and the other headers can be checked before deploying.
// It covers only the route features vercel-output.mjs writes.
import { existsSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { brotliCompressSync } from 'node:zlib'

const OUTPUT = '.vercel/output'

/**
 * Reads the routing config, fresh on every request so a rebuild needs no restart.
 *
 * @returns {{ routes: object[], overrides: Record<string, { path: string }> }} The config.
 */
function load() {
  return JSON.parse(readFileSync(join(OUTPUT, 'config.json'), 'utf8'))
}

/**
 * Finds the static file a path is served from.
 *
 * @param {string} pathname The request path.
 * @param {Record<string, { path: string }>} overrides The config's clean-URL overrides.
 * @returns {string | null} The file's path inside the output, or null.
 */
function resolve(pathname, overrides) {
  let paths = Object.fromEntries(
    Object.entries(overrides).map(([file, { path }]) => [`/${path}`, file]),
  )
  let file =
    pathname === '/' ? 'index.html' : (paths[pathname] ?? pathname.slice(1))
  let full = join(OUTPUT, 'static', file)
  return existsSync(full) && statSync(full).isFile() ? full : null
}

const COMPRESSIBLE = /^(text\/|application\/(javascript|json|xml)|image\/svg)/

/**
 * Serves a static file, compressed with Brotli when the client accepts it, as
 * Vercel's CDN does, so local measurements see production transfer sizes.
 *
 * @param {Request} request The incoming request.
 * @param {string} path The file to serve.
 * @param {number} status The response status.
 * @param {Record<string, string>} headers The headers the routes collected.
 * @returns {Promise<Response>} The response.
 */
async function serve(request, path, status, headers) {
  let file = Bun.file(path)
  let type = file.type

  if (
    !COMPRESSIBLE.test(type) ||
    !request.headers.get('accept-encoding')?.includes('br')
  ) {
    return new Response(file, { status, headers })
  }

  let body = brotliCompressSync(Buffer.from(await file.arrayBuffer()))
  return new Response(body, {
    status,
    headers: { ...headers, 'Content-Type': type, 'Content-Encoding': 'br' },
  })
}

Bun.serve({
  port: Number(process.env.PORT ?? 3000),
  async fetch(request) {
    let { pathname } = new URL(request.url)
    let config = load()
    let headers = {}

    // Vercel serves Web Analytics' script itself; locally it is an empty stub,
    // so pages load without the 404 a missing script would log.
    if (pathname.startsWith('/_vercel/insights/')) {
      return new Response('', {
        headers: { 'Content-Type': 'text/javascript' },
      })
    }

    for (let route of config.routes) {
      if (route.handle === 'filesystem') {
        let file = resolve(pathname, config.overrides)
        if (file) return serve(request, file, 200, headers)
        continue
      }

      let match = new RegExp(route.src).exec(pathname)
      if (!match) continue

      let values = Object.fromEntries(
        Object.entries(route.headers ?? {}).map(([key, value]) => [
          key,
          value.replace(/\$(\d)/g, (_, index) => match[index] ?? ''),
        ]),
      )
      Object.assign(headers, values)

      if (route.continue) continue
      if (route.dest) {
        return serve(
          request,
          join(OUTPUT, 'static', route.dest),
          route.status ?? 200,
          headers,
        )
      }
      return new Response(null, { status: route.status ?? 200, headers })
    }

    return new Response('Not found', { status: 404, headers })
  },
})

console.log(
  `Previewing ${OUTPUT} at http://localhost:${process.env.PORT ?? 3000}`,
)
