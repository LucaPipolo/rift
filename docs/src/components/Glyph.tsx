import clsx from 'clsx'

const glyphs = {
  installation: <path d="M12 3v11m0 0-4-4m4 4 4-4M4 15v5h16v-5" />,
  presets: <path d="M7 4.5v15L19 12 7 4.5Z" />,
  plugins: <path d="M4 7h10m4 0h2M4 17h2m4 0h10M14 4v6M6 14v6" />,
  theming: <path d="M6 3v18M18 3v5c0 5-12 4-12 10" />,
  lightbulb: (
    <path d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.5 10.9V16h7v-2.1A6 6 0 0 0 12 3Z" />
  ),
  warning: <path d="M12 3 2.5 20h19L12 3Zm0 6v5m0 3v.5" />,
  light: (
    <path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0-5v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4M5.6 18.4 7 17m10-10 1.4-1.4" />
  ),
  dark: <path d="M19.5 14.5A8 8 0 0 1 9.5 4.5a8 8 0 1 0 10 10Z" />,
  system: <path d="M3 4h18v12H3V4Zm6 16h6m-3-4v4" />,
  menu: <path d="M4 7h16M4 12h16M4 17h16" />,
  close: <path d="m6 6 12 12M18 6 6 18" />,
  github: (
    <path
      fill="var(--color-ink)"
      stroke="none"
      d="M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.7c-2.78.62-3.37-1.36-3.37-1.36-.45-1.18-1.11-1.5-1.11-1.5-.91-.64.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.89 1.57 2.34 1.12 2.91.85.09-.66.35-1.12.63-1.37-2.22-.26-4.56-1.14-4.56-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.71 0 0 .84-.28 2.75 1.05A9.4 9.4 0 0 1 12 6.84c.85 0 1.7.12 2.5.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.41.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.81-4.57 5.06.36.32.68.94.68 1.9v2.81c0 .27.18.6.69.49A10.25 10.25 0 0 0 22 12.25C22 6.58 17.52 2 12 2Z"
    />
  ),
}

const colors = {
  installation: 'bg-sun',
  presets: 'bg-volt',
  plugins: 'bg-blush',
  theming: 'bg-mint',
  lightbulb: 'bg-apricot',
  warning: 'bg-tomato',
  light: 'bg-sun',
  dark: 'bg-blush',
  system: 'bg-volt',
  menu: 'bg-white',
  close: 'bg-white',
  github: 'bg-white',
}

export type GlyphName = keyof typeof glyphs

/**
 * A line icon on a square block of its own colour, the site's icon style.
 */
export function Glyph({
  name,
  className,
}: {
  name: GlyphName
  className?: string
}) {
  return (
    <span
      aria-hidden="true"
      className={clsx(
        'flex flex-none items-center justify-center border-2 border-ink',
        colors[name],
        className,
      )}
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="var(--color-ink)"
        strokeWidth={2}
        strokeLinejoin="round"
        className="h-1/2 w-1/2"
      >
        {glyphs[name]}
      </svg>
    </span>
  )
}
