import Link from 'next/link'

const legal = [
  { title: 'Legal notice', href: '/legal-notice' },
  { title: 'Privacy', href: '/privacy' },
  { title: 'Cookies', href: '/cookies' },
  { title: 'Security', href: '/security' },
  { title: 'AI content', href: '/ai-content' },
]

/**
 * The site footer: the copyright line, and the legal pages.
 */
export function Footer() {
  return (
    <footer className="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="mx-auto flex max-w-8xl flex-col gap-2 px-4 py-4 text-xs text-slate-600 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 xl:px-12 dark:text-slate-400">
        <p>© 2026 Rift. Every agent gets its own running app.</p>
        <nav aria-label="Legal">
          <ul role="list" className="flex flex-wrap gap-x-4 gap-y-1">
            {legal.map((link) => (
              <li key={link.href}>
                <Link
                  href={link.href}
                  className="hover:text-ink hover:underline dark:hover:text-white"
                >
                  {link.title}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </footer>
  )
}
