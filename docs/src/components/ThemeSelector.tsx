import { useEffect, useState } from 'react'
import { useTheme } from 'next-themes'
import {
  Label,
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from '@headlessui/react'
import clsx from 'clsx'

import { Glyph } from '@/components/Glyph'

const themes = [
  { name: 'Light', value: 'light' },
  { name: 'Dark', value: 'dark' },
  { name: 'System', value: 'system' },
] as const

export function ThemeSelector(
  props: React.ComponentPropsWithoutRef<typeof Listbox<'div'>>,
) {
  let { theme, setTheme } = useTheme()
  let [mounted, setMounted] = useState(false)

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setMounted(true)
  }, [])

  if (!mounted) {
    return <div className="h-8 w-8" />
  }

  return (
    <Listbox as="div" value={theme} onChange={setTheme} {...props}>
      <Label className="sr-only">Theme</Label>
      <ListboxButton className="block" aria-label="Theme">
        <span className="dark:hidden">
          <Glyph name="light" className="h-8 w-8" />
        </span>
        <span className="hidden dark:block">
          <Glyph name="dark" className="h-8 w-8" />
        </span>
      </ListboxButton>
      <ListboxOptions className="absolute top-full left-1/2 mt-3 w-40 -translate-x-1/2 space-y-1 border-2 border-ink bg-white p-2 text-sm font-medium focus:outline-none dark:border-slate-700 dark:bg-slate-800">
        {themes.map((theme) => (
          <ListboxOption
            key={theme.value}
            value={theme.value}
            className={({ focus, selected }) =>
              clsx('flex cursor-pointer items-center p-1 select-none', {
                'font-semibold text-ink dark:text-white': selected,
                'text-slate-900 dark:text-white': focus && !selected,
                'text-slate-700 dark:text-slate-400': !focus && !selected,
                'bg-slate-100 dark:bg-slate-900': focus,
              })
            }
          >
            <Glyph name={theme.value} className="h-7 w-7" />
            <div className="ml-3">{theme.name}</div>
          </ListboxOption>
        ))}
      </ListboxOptions>
    </Listbox>
  )
}
