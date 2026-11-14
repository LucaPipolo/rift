import clsx from 'clsx'

// The wordmark: "rift" with a tangerine square for the dot of the i.
export function Logo({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={clsx(
        'font-display text-[2rem] leading-none font-bold tracking-tight text-ink dark:text-white',
        className,
      )}
    >
      r
      <span className="relative">
        ı
        <span className="absolute top-[0.15em] left-1/2 h-[0.22em] w-[0.22em] -translate-x-1/2 bg-tangerine" />
      </span>
      ft
    </span>
  )
}
