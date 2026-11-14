import Link from 'next/link'

import { ExternalLinkIcon, isExternal } from '@/components/ExternalLinkIcon'

/**
 * A link in a page's Markdown: external ones open in a new tab and carry an
 * icon, internal ones navigate within the site.
 */
export function DocLink({
  href,
  title,
  children,
}: {
  href: string
  title?: string
  children: React.ReactNode
}) {
  if (isExternal(href)) {
    return (
      <a href={href} title={title} target="_blank" rel="noopener noreferrer">
        {children}
        <ExternalLinkIcon />
      </a>
    )
  }

  return (
    <Link href={href} title={title}>
      {children}
    </Link>
  )
}
