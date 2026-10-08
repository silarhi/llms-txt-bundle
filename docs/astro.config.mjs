import { defineSilarhiDocs } from '@silarhi/docs-kit'

export default defineSilarhiDocs({
    slug: 'llms-txt-bundle',
    title: 'LLMs.txt Bundle',
    description: 'Build, dump and serve an llms.txt file from your Symfony application.',
    sidebar: [
        { label: 'Getting started', items: [{ autogenerate: { directory: 'getting-started' } }] },
        { label: 'Guides', items: [{ autogenerate: { directory: 'guides' } }] },
    ],
})
