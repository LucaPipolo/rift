import clsx from 'clsx'

export function Logomark(props: React.ComponentPropsWithoutRef<'svg'>) {
  return (
    <svg aria-hidden="true" viewBox="0 0 36 36" {...props}>
      <rect
        x={7}
        y={7}
        width={22}
        height={22}
        fill="var(--color-tangerine)"
        stroke="var(--color-ink)"
        strokeWidth={2}
      />
    </svg>
  )
}

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
