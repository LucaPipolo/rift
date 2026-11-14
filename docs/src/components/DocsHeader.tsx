'use client'

import { usePathname } from 'next/navigation'

import { navigation } from '@/lib/navigation'

export function DocsHeader({ title }: { title?: string }) {
  let pathname = usePathname()
  let section = navigation.find((section) =>
    section.links.find((link) => link.href === pathname),
  )

  if (!title && !section) {
    return null
  }

  return (
    <header className="mb-9 space-y-3">
      {section && (
        <p className="inline-block bg-volt px-1.5 py-0.5 font-display text-xs font-bold tracking-wider text-ink uppercase">
          {section.title}
        </p>
      )}
      {title && (
        <h1 className="font-display text-4xl font-bold tracking-tight text-ink dark:text-white">
          {title}
        </h1>
      )}
    </header>
  )
}
