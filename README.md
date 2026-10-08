<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/license/silarhi/llms-txt-bundle?style=for-the-badge&color=6f42c1&labelColor=1a1a2e">
        <img src="https://img.shields.io/github/license/silarhi/llms-txt-bundle?style=for-the-badge&color=6f42c1" alt="License">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/badge/php-%3E%3D8.2-777bb4?style=for-the-badge&labelColor=1a1a2e">
        <img src="https://img.shields.io/badge/php-%3E%3D8.2-777bb4?style=for-the-badge" alt="PHP Version">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/actions/workflow/status/silarhi/llms-txt-bundle/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997&labelColor=1a1a2e">
        <img src="https://img.shields.io/github/actions/workflow/status/silarhi/llms-txt-bundle/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997"
            alt="CI Status">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fllms-txt-bundle%2Fbadges%2Fcoverage.json&style=for-the-badge&labelColor=1a1a2e">
        <img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fllms-txt-bundle%2Fbadges%2Fcoverage.json&style=for-the-badge" alt="Coverage">
    </picture>
</p>

<h1 align="center">LLMs.txt Bundle</h1>

<p align="center">
    <strong>Build, dump and serve an <a href="https://llmstxt.org">llms.txt</a> file from your Symfony application.</strong><br>
    The <a href="https://github.com/prestaconcept/PrestaSitemapBundle">PrestaSitemapBundle</a> way: listeners fill it, a controller serves it, a command dumps it.
</p>

<p align="center">
    📖 <a href="https://llms-txt-bundle.silarhi.dev"><strong>Documentation</strong></a>
</p>

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Adding links](#adding-links)
    - [From an event listener](#from-an-event-listener)
    - [Lots of links: generators](#lots-of-links-generators)
    - [From route options](#from-route-options)
- [Serving the file](#serving-the-file)
    - [On the fly](#on-the-fly)
    - [Dumped](#dumped)
- [Discovery](#discovery)
- [License](#license)

## Features

- **Event driven**: listeners of `LlmsTxtPopulateEvent` add the links, from your repositories, like `SitemapPopulateEvent`
- **Route options**: static pages join the file with an `llms_txt` route option, like the `sitemap` one
- **On the fly or dumped**: the controller serves the dumped file when there is one, and builds it otherwise
- **Flat memory**: links added as generators are read one at a time, streamed to the response or the dumped file
- **Spec compliant**: H1 title, blockquote summary, H2 sections of links, the `Optional` section always last, escaped links
- **Discovery**: a `<link>` tag for the `<head>` of your pages and an opt-in `Link` header, through [WebLink](https://symfony.com/doc/current/web_link.html)

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `symfony/console` for the `llms-txt:dump` command, `symfony/twig-bundle` for the Twig functions

## Installation

```bash
composer require silarhi/llms-txt-bundle
```

Register the bundle if Flex did not:

```php
// config/bundles.php
return [
    // ...
    Silarhi\LlmsTxtBundle\LlmsTxtBundle::class => ['all' => true],
];
```

Import the route serving `/llms.txt`:

```yaml
# config/routes/llms_txt.yaml
llms_txt:
    resource: '@LlmsTxtBundle/config/routes.php'
```

## Configuration

```yaml
# config/packages/llms_txt.yaml
llms_txt:
    # The H1 of the file, the name of the site (required, unless a listener sets it)
    title: 'SILARHI'
    # A short summary, rendered as a blockquote
    summary: 'Agence de développement Web PHP à Toulouse : applications Web et mobiles sur mesure, de la conception à la maintenance.'
    # Free Markdown rendered after the summary: paragraphs, lists, anything but headings
    details: |
        Devis rapide et gratuit, interventions à Toulouse et partout en France.
    # The section of the routes carrying an "llms_txt" option without one of their own
    route_section: 'Pages'
    # Where llms-txt:dump writes the file, and where the controller looks for it first
    dump_directory: '%kernel.project_dir%/public'
    # "text/markdown" is more accurate, but browsers download it instead of showing it
    content_type: 'text/plain; charset=UTF-8'
    # Cache-Control max-age of the controller responses, in seconds
    max_age: 3600
    discovery:
        # Adds the file to the Link header of the HTML pages, through WebLink
        link_header: false
        # The relation of the Link header and of the llms_txt_link() tag
        rel: 'llms-txt'
```

## Adding links

### From an event listener

```php
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LlmsTxtListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ProjectRepository $projectRepository,
    ) {
    }

    #[AsEventListener]
    public function __invoke(LlmsTxtPopulateEvent $event): void
    {
        $document = $event->getDocument();

        foreach ($this->projectRepository->findBy(['published' => true]) as $project) {
            $document->addLink(
                'Projets',
                $this->urlGenerator->generate('project_show', ['slug' => $project->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                $project->getName(),
                $project->getSummary(), // optional description
            );
        }
    }
}
```

The document can also be changed as a whole: `setTitle()`, `setSummary()`, `setDetails()`, or `section('Projets')->addLink(new Link(...))`. Sections keep the order of their first use, except `Optional` ([its links can be skipped](https://llmstxt.org/#format) when a shorter context is needed), which always comes last.

> [!TIP]
> Keep the file consistent with your sitemap: add the pages you index, not the `noindex` ones.

### Lots of links: generators

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

### From route options

```php
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;

#[Route('/contact', name: 'contact', options: ['llms_txt' => new LlmsTxtEntry(
    title: 'Contact',                      // required
    description: 'How to reach the team',  // optional
)])]
public function contact(): Response

#[Route('/cgu', name: 'cgu', options: ['llms_txt' => new LlmsTxtEntry(title: 'CGU', section: Section::OPTIONAL)])]
public function cgu(): Response
```

`section` defaults to `route_section`. An empty title or section fails as soon as the routes load. Routes declared in
YAML or XML take the same keys as an array:

```yaml
contact:
    path: /contact
    controller: App\Controller\ContactController
    options:
        llms_txt: { title: Contact, description: How to reach the team }
```

Only routes without mandatory parameters can carry the option: add the others from a listener.

## Serving the file

### On the fly

With the route imported, `GET /llms.txt` builds the file on each request, with a public `Cache-Control` of `max_age`
seconds. The file is rendered whole before the response starts, into a `php://temp` buffer that spills to a temporary
file past 256 KiB, so the memory stays flat and:

- a listener failing half way gives an error page, never a truncated `200` left in the caches
- the response carries a `Content-Length` and an `ETag`, and answers `304 Not Modified` to a matching `If-None-Match`

### Dumped

```bash
php bin/console llms-txt:dump
php bin/console llms-txt:dump --base-url=https://example.com   # without "framework.router.default_uri"
php bin/console llms-txt:dump var/llms_txt                     # another directory
```

The file is streamed to a temporary file next to it, renamed once complete: the web server never serves a half written
file, and a failing listener leaves the previous one in place.

- **Into `public/`** (the default): the web server serves it without booting Symfony. The route is a fallback until the first dump: remember to dump again, from a cron or after a deployment, or the file goes stale.
- **Elsewhere** (`dump_directory: '%kernel.project_dir%/var/llms_txt'`): the controller serves the dumped file when there is one, with `ETag` and `Last-Modified`, and builds it on the fly otherwise.

## Discovery

The [llms.txt proposal](https://llmstxt.org) only sets the location of the file, `/llms.txt`. Three ways to advertise
it, to combine as you like:

```twig
{# templates/base.html.twig #}
<head>
    {# a <link> tag #}
    {{ llms_txt_link() }}
    {# <link rel="llms-txt" href="https://example.com/llms.txt"> #}

    {{ llms_txt_link({rel: 'alternate', type: 'text/markdown', title: 'LLMs.txt'}) }}
    {# <link rel="alternate" href="https://example.com/llms.txt" type="text/markdown" title="LLMs.txt"> #}

    {# the Link header of this page only, with the link() function of WebLink #}
    {% do link(llms_txt_url(), 'llms-txt') %}
</head>
```

```yaml
# the Link header of every HTML page
llms_txt:
    discovery:
        link_header: true # Link: <https://example.com/llms.txt>; rel="llms-txt"
```

`link_header` goes through [WebLink](https://symfony.com/doc/current/web_link.html): the link joins the `_links` of
the request, and WebLink writes them all in one `Link` header, next to your `preload()` and `preconnect()` ones. It
needs `framework.web_link`, enabled by default when `symfony/web-link` is installed (a dependency of the bundle). The
header is added to successful HTML responses of main requests only: not to sub-requests, Turbo Frames, XHR, redirects,
errors, JSON or the `llms.txt` file itself.

`llms_txt_url()` returns the absolute URL alone. Both use the route of the bundle when it is imported, and `/llms.txt`
at the root of the site otherwise.

## License

Released under the [MIT License](LICENSE).
