import clsx from 'clsx'

import { Glyph } from '@/components/Glyph'

const styles = {
  note: {
    container: 'border-ink bg-volt/25 dark:border-volt dark:bg-volt/10',
    glyph: 'lightbulb',
  },
  warning: {
    container: 'border-ink bg-blush/30 dark:border-blush dark:bg-blush/10',
    glyph: 'warning',
  },
} as const

export function Callout({
  title,
  children,
  type = 'note',
}: {
  title: string
  children: React.ReactNode
  type?: keyof typeof styles
}) {
  return (
    <div className={clsx('my-8 flex border-2 p-6', styles[type].container)}>
      <Glyph name={styles[type].glyph} className="h-10 w-10" />
      <div className="ml-4 flex-auto">
        <p className="not-prose font-display text-lg font-semibold text-ink dark:text-white">
          {title}
        </p>
        <div className="prose mt-2 text-ink dark:text-slate-300 prose-code:text-ink dark:prose-code:text-slate-200">
          {children}
        </div>
      </div>
    </div>
  )
}
