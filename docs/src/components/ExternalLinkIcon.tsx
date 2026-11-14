import clsx from 'clsx'

/**
 * Marks a link that leaves the site, and tells screen readers it opens a new tab.
 */
export function ExternalLinkIcon({ className }: { className?: string }) {
  return (
    <>
      <svg
        aria-hidden="true"
        viewBox="0 0 12 12"
        fill="none"
        stroke="currentColor"
        strokeWidth={1.75}
        strokeLinecap="square"
        className={clsx(
          'ml-0.5 inline-block h-[0.7em] w-[0.7em] align-baseline',
          className,
        )}
      >
        <path d="M4 2h6v6M10 2 2 10" />
      </svg>
      <span className="sr-only"> (opens in a new tab)</span>
    </>
  )
}

/**
 * Determine whether an href leaves the site.
 */
export function isExternal(href: string) {
  return /^https?:\/\//.test(href)
}
