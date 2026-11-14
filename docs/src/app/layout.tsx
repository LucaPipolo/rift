import { type Metadata } from 'next'
import { Inter, Space_Grotesk } from 'next/font/google'
import clsx from 'clsx'

import { Providers } from '@/app/providers'
import { Layout } from '@/components/Layout'

import '@/styles/tailwind.css'

const inter = Inter({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-inter',
})

const spaceGrotesk = Space_Grotesk({
  // latin-ext carries the dotless i the wordmark is built on.
  subsets: ['latin', 'latin-ext'],
  display: 'swap',
  variable: '--font-space-grotesk',
})

export const metadata: Metadata = {
  title: {
    template: '%s - Rift',
    default: 'Rift - Every agent gets its own running app',
  },
  description:
    'Run AI agents in parallel, each on its own Git worktree and its own running app, with separate containers, ports and a local HTTPS address. For Docker Compose and Laravel Sail projects.',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html
      lang="en"
      className={clsx(
        'h-full antialiased',
        inter.variable,
        spaceGrotesk.variable,
      )}
      suppressHydrationWarning
    >
      <body className="flex min-h-full bg-white text-ink dark:bg-slate-900">
        <Providers>
          <Layout>{children}</Layout>
        </Providers>
      </body>
    </html>
  )
}
