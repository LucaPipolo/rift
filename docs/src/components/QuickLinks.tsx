import Link from 'next/link'

import { Glyph, type GlyphName } from '@/components/Glyph'

export function QuickLinks({ children }: { children: React.ReactNode }) {
  return (
    <div className="not-prose my-12 grid grid-cols-1 gap-6 sm:grid-cols-2">
      {children}
    </div>
  )
}

export function QuickLink({
  title,
  description,
  href,
  icon,
}: {
  title: string
  description: string
  href: string
  icon: GlyphName
}) {
  return (
    <div className="group relative border-2 border-ink bg-white p-6 transition-transform hover:-translate-x-1 hover:-translate-y-1 hover:shadow-[6px_6px_0_0_var(--color-ink)] dark:border-slate-700 dark:bg-slate-800 dark:hover:shadow-[6px_6px_0_0_var(--color-tangerine)]">
      <Glyph name={icon} className="h-12 w-12" />
      <h2 className="mt-4 font-display text-lg font-semibold text-ink dark:text-white">
        <Link href={href}>
          <span className="absolute -inset-0.5" />
          {title}
        </Link>
      </h2>
      <p className="mt-1 text-sm text-slate-700 dark:text-slate-400">
        {description}
      </p>
    </div>
  )
}
