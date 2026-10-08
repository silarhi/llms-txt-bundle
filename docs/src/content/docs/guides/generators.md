---
title: 'Lots of links: generators'
description: Keep the memory flat with generators and keyset pagination, whatever the number of links.
sidebar:
    order: 2
---

`addLink()` keeps each link in memory until the file is rendered. For large sections, hand `addLinks()` an iterable
instead: it is only read while the file is rendered, one link at a time, and each line goes to the buffer of the
response or to the dumped file as soon as it is rendered. With a generator, the memory stays flat whatever the number of links
(200,000 links, a 17 MB file: about 1 MB of memory, against about 100 MB when they are all held).

Read the rows by pages too. Doctrine's `toIterable()` does not keep the memory flat: it hydrates one row at a time, but
`pdo_mysql` buffers the whole result set client-side, and the entity manager keeps every entity it hydrated.
[silarhi/cursor-pagination](https://github.com/silarhi/cursor-pagination) reads them by keyset pages instead, each query
starting after the last row of the previous one; selecting scalar fields skips the hydration and the identity map:

```bash
composer require silarhi/cursor-pagination
```

```php
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

#[AsEventListener]
public function __invoke(LlmsTxtPopulateEvent $event): void
{
    $event->getDocument()->addLinks('Projets', $this->projectLinks());
}

/**
 * @return iterable<Link>
 */
private function projectLinks(): iterable
{
    $queryBuilder = $this->projectRepository->createQueryBuilder('p')
        ->select('p.id, p.slug, p.name, p.summary')
        ->where('p.published = true');

    /** @var CursorPagination<array{id: int, slug: string, name: string, summary: string|null}> $pagination */
    $pagination = new CursorPagination(
        $queryBuilder,
        // the order of the pages; it must be unique: close a non-unique one (a date, a name…) with the id
        new OrderConfigurations(new OrderConfiguration('p.id', static fn (array $project): int => $project['id'], isUnique: true)),
        500,
        // scalar rows: no collection to fetch-join
        fetchJoinCollection: false,
    );

    foreach ($pagination->getResults() as $project) {
        yield new Link(
            $this->urlGenerator->generate('project_show', ['slug' => $project['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
            $project['name'],
            $project['summary'],
        );
    }
}
```

The generator runs after your listener returns, while the file is rendered: it can be read once, so a document is
rendered once. The title is checked before any link is read: a missing title fails with an error page or a failing
command, never with a truncated file.
