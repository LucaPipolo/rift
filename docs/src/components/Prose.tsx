import clsx from 'clsx'

export function Prose<T extends React.ElementType = 'div'>({
  as,
  className,
  ...props
}: React.ComponentPropsWithoutRef<T> & {
  as?: T
}) {
  let Component = as ?? 'div'

  return (
    <Component
      className={clsx(
        className,
        'prose max-w-none prose-slate dark:text-slate-400 dark:prose-invert',
        // headings
        'prose-headings:scroll-mt-28 prose-headings:font-display prose-headings:font-semibold prose-headings:text-ink lg:prose-headings:scroll-mt-34 dark:prose-headings:text-white',
        // lead
        'prose-lead:text-slate-600 dark:prose-lead:text-slate-400',
        // links
        'prose-a:font-semibold prose-a:text-ink dark:prose-a:text-white',
        // link underline
        'dark:[--tw-prose-background:var(--color-slate-900)] prose-a:no-underline prose-a:shadow-[inset_0_-2px_0_0_var(--tw-prose-background,#fff),inset_0_calc(-1*(var(--tw-prose-underline-size,4px)+2px))_0_0_var(--tw-prose-underline,var(--color-volt))] prose-a:hover:[--tw-prose-underline-size:6px] dark:prose-a:shadow-[inset_0_calc(-1*var(--tw-prose-underline-size,2px))_0_0_var(--tw-prose-underline,var(--color-tangerine))] dark:prose-a:hover:[--tw-prose-underline-size:6px]',
        // pre
        'prose-pre:border-2 prose-pre:border-ink prose-pre:bg-ink dark:prose-pre:border-slate-700 dark:prose-pre:bg-slate-950',
        // inline code
        'prose-code:bg-slate-100 prose-code:px-1 prose-code:py-0.5 prose-code:font-medium prose-code:before:content-none prose-code:after:content-none dark:prose-code:bg-slate-800 prose-pre:prose-code:bg-transparent prose-pre:prose-code:p-0',
        // hr
        'dark:prose-hr:border-slate-800',
      )}
      {...props}
    />
  )
}
