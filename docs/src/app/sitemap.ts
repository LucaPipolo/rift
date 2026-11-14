import { type MetadataRoute } from 'next'

import { navigation } from '@/lib/navigation'

export const dynamic = 'force-static'

const legal = [
  '/legal-notice',
  '/privacy',
  '/cookies',
  '/security',
  '/ai-content',
]

export default function sitemap(): MetadataRoute.Sitemap {
  let paths = [
    ...navigation.flatMap((section) => section.links.map((link) => link.href)),
    ...legal,
  ]

  return paths.map((path) => ({
    url: new URL(path, 'https://getrift.sh').toString(),
  }))
}
