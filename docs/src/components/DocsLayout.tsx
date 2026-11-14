import { type Node } from '@markdoc/markdoc'

import { DocsHeader } from '@/components/DocsHeader'
import { PrevNextLinks } from '@/components/PrevNextLinks'
import { Prose } from '@/components/Prose'
import { TableOfContents } from '@/components/TableOfContents'
import { collectSections } from '@/lib/sections'

type Frontmatter = {
  title?: string
  nextjs?: {
    metadata?: { title?: string | { absolute: string }; description?: string }
  }
}

/**
 * Get a page's document title from its frontmatter.
 *
 * @param frontmatter The page's frontmatter.
 * @returns The title, suffixed with the site's name unless it is absolute.
 */
function documentTitle({ title, nextjs }: Frontmatter) {
  let metadataTitle = nextjs?.metadata?.title

  if (typeof metadataTitle === 'object') return metadataTitle.absolute

  return `${metadataTitle ?? title} - Rift`
}

export function DocsLayout({
  children,
  frontmatter,
  nodes,
}: {
  children: React.ReactNode
  frontmatter: Frontmatter
  nodes: Array<Node>
}) {
  let { title } = frontmatter
  let description = frontmatter.nextjs?.metadata?.description
  let tableOfContents = collectSections(nodes)

  // Markdoc pages are compiled as client modules, which cannot export
  // metadata, so React hoists these into the document's head instead.
  return (
    <>
      <title>{documentTitle(frontmatter)}</title>
      {description && <meta name="description" content={description} />}
      <div className="max-w-2xl min-w-0 flex-auto px-4 py-16 lg:max-w-none lg:pr-0 lg:pl-8 xl:px-16">
        <article>
          <DocsHeader title={title} />
          <Prose>{children}</Prose>
        </article>
        <PrevNextLinks />
      </div>
      <TableOfContents tableOfContents={tableOfContents} />
    </>
  )
}
