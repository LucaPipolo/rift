import withMarkdoc from '@markdoc/next.js'

import withSearch from './src/markdoc/search.mjs'

/** @type {import('next').NextConfig} */
const nextConfig = {
  // A static export, so the site needs no server and any static host can serve it.
  output: 'export',
  images: { unoptimized: true },
  // The repository root has its own unrelated lockfile, for the commit tooling.
  outputFileTracingRoot: import.meta.dirname,
  pageExtensions: ['js', 'jsx', 'md', 'ts', 'tsx'],
}

export default withSearch(
  withMarkdoc({ schemaPath: './src/markdoc', nextjsExports: ['revalidate'] })(
    nextConfig,
  ),
)
