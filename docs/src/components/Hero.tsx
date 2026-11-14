import { Button } from '@/components/Button'
import { ExternalLinkIcon } from '@/components/ExternalLinkIcon'

const lines = [
  { prompt: true, text: 'git worktree add -b search ../myapp-search' },
  { prompt: true, text: 'cd ../myapp-search' },
  { prompt: true, text: 'rift setup' },
  { prompt: false, text: 'https://search.myapp.test' },
]

function Terminal() {
  return (
    <div className="relative z-10 border-2 border-ink bg-white">
      <div className="flex items-center justify-between border-b-2 border-ink bg-paper px-4 py-2">
        <div className="flex gap-1.5" aria-hidden="true">
          <span className="h-3 w-3 border-2 border-ink bg-tomato" />
          <span className="h-3 w-3 border-2 border-ink bg-sun" />
          <span className="h-3 w-3 border-2 border-ink bg-mint" />
        </div>
        <span className="font-display text-xs font-semibold tracking-wider text-ink uppercase">
          Terminal
        </span>
      </div>
      <pre className="overflow-x-auto p-5 font-mono text-sm leading-7 text-ink">
        <code>
          {lines.map((line) =>
            line.prompt ? (
              <div key={line.text}>
                <span className="text-slate-500 select-none">$ </span>
                {line.text}
              </div>
            ) : (
              <div key={line.text} className="mt-2">
                <span className="bg-volt px-1.5 py-0.5 font-semibold">
                  {line.text}
                </span>
              </div>
            ),
          )}
        </code>
      </pre>
    </div>
  )
}

// One project branching into three running copies, in the flat geometric
// style of the rest of the site.
function Branches() {
  return (
    <svg
      viewBox="0 0 320 150"
      className="mx-auto -mt-6 w-full max-w-sm"
      aria-hidden="true"
    >
      <g stroke="var(--color-ink)" strokeWidth={2} fill="none">
        <path d="M160 30v40M160 70H54v30M160 70v30M160 70h106v30" />
      </g>
      <circle
        cx={160}
        cy={30}
        r={22}
        fill="var(--color-tangerine)"
        stroke="var(--color-ink)"
        strokeWidth={2}
      />
      <path
        d="M160 20v20M150 30h20"
        stroke="white"
        strokeWidth={2.5}
        strokeLinecap="square"
      />
      <rect
        x={14}
        y={100}
        width={80}
        height={46}
        fill="var(--color-apricot)"
        stroke="var(--color-ink)"
        strokeWidth={2}
      />
      <circle
        cx={160}
        cy={123}
        r={23}
        fill="var(--color-mint)"
        stroke="var(--color-ink)"
        strokeWidth={2}
      />
      <rect
        x={226}
        y={100}
        width={80}
        height={46}
        fill="var(--color-tomato)"
        stroke="var(--color-ink)"
        strokeWidth={2}
      />
    </svg>
  )
}

export function Hero() {
  return (
    <div className="overflow-hidden border-b-2 border-ink bg-sun">
      <div className="py-16 sm:px-2 lg:relative lg:px-0 lg:py-20">
        <div className="mx-auto grid max-w-2xl grid-cols-1 items-center gap-x-8 gap-y-16 px-4 lg:max-w-8xl lg:grid-cols-2 lg:px-8 xl:gap-x-16 xl:px-12">
          <div className="relative z-10 md:text-center lg:text-left">
            <p className="inline-block border-2 border-ink bg-white px-2 py-0.5 font-display text-xs font-semibold tracking-wider text-ink uppercase">
              Made for parallel AI agents
            </p>
            <h1 className="mt-6 font-display text-5xl font-bold tracking-tight text-ink sm:text-6xl">
              Every agent gets its own running app.
            </h1>
            <p className="mt-6 text-xl text-ink/80">
              Give each AI agent a Git worktree and Rift runs it as a separate
              copy of your app, with its own containers, ports and HTTPS
              address. Review their work side by side.
            </p>
            <div className="mt-8 flex gap-4 md:justify-center lg:justify-start">
              <Button href="/docs/quick-start">Get started</Button>
              <Button
                href="https://github.com/LucaPipolo/rift"
                target="_blank"
                rel="noopener noreferrer"
                variant="secondary"
              >
                View on GitHub
                <ExternalLinkIcon className="ml-1.5" />
              </Button>
            </div>
          </div>
          <div className="relative lg:pl-10">
            <div
              aria-hidden="true"
              className="absolute -top-8 -left-2 h-24 w-24 border-2 border-ink bg-volt lg:left-2"
            />
            <div
              aria-hidden="true"
              className="absolute -top-10 right-6 h-20 w-20 rounded-full border-2 border-ink bg-blush"
            />
            <Terminal />
            <Branches />
          </div>
        </div>
      </div>
    </div>
  )
}
