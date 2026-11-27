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

## Features

- **Link providers**: a `#[AsLlmsTxtLinkProvider(section: 'Products')]` method returns the links of a section, from your repositories
- **Event driven**: listeners of `LlmsTxtPopulateEvent` change the whole document, like `SitemapPopulateEvent`
- **Route options**: static pages join the file with an `llms_txt` route option, like the `sitemap` one
- **On the fly or dumped**: the controller serves the dumped file when there is one, and builds it otherwise
- **Flat memory**: links added as generators are read one at a time, streamed to the response or the dumped file
- **llms-full.txt**: the Markdown of the pages, next to `llms.txt`, from the `content` of their links
- **Markdown versions**: a route accepting the `md` format serves its page at `page.md`, and to the agents asking for `text/markdown`
- **Checks**: `debug:llms-txt` shows where the links come from, and finds duplicates, relative URLs, a file too large
- **Spec compliant**: H1 title, blockquote summary, H2 sections of links, the `Optional` section always last, escaped links
- **Discovery**: a `<link>` tag for the `<head>` of your pages and an opt-in `Link` header, through [WebLink](https://symfony.com/doc/current/web_link.html)

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `symfony/framework-bundle` to register the bundle: the package does not install it, and the generator, the renderers
  and the dumpers work without it, see [Without the bundle](#without-the-bundle)

## Installation

```bash
composer require silarhi/llms-txt-bundle
```

Import the route serving `/llms.txt`:

```yaml
# config/routes/llms_txt.yaml
llms_txt:
    resource: '@LlmsTxtBundle/config/routes.php'
```

### Without the bundle

An application without the framework or `symfony/dependency-injection` builds the services by hand, from an event
dispatcher and the router (the `llms_txt` route options):

```php
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\LlmsTxt;
use Silarhi\LlmsTxtBundle\Service\Dumper;
use Silarhi\LlmsTxtBundle\Service\FullMarkdownRenderer;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new RouteOptionsListener($router, 'Pages')); // the routes carrying an "llms_txt" option
$dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event) use ($products): void {
    $event->getDocument()->addLinks('Products', $products->links()); // a generator of Link, read while writing
});

$generator = new Generator($dispatcher, title: 'SILARHI', summary: 'Web and mobile applications.');

// public/llms.txt, and public/llms-full.txt from the same listeners
(new Dumper($generator, new MarkdownRenderer(), new Filesystem(), __DIR__ . '/public', LlmsTxt::FILENAME))->dump();
(new Dumper($generator, new FullMarkdownRenderer(), new Filesystem(), __DIR__ . '/public', LlmsTxt::FULL_FILENAME))->dump();

// or serve it: the dumped file when there is one, built on the fly otherwise
$controller = new LlmsTxtController($generator, new MarkdownRenderer(), __DIR__ . '/public', 'text/plain; charset=UTF-8', 3600, LlmsTxt::FILENAME, true);
$response = $controller($request);
```

`DumpCommand` (with `symfony/console`), `LlmsTxtExtension` (with `twig/twig`) and `MarkdownVersionListener` are built
the same way. What needs a container stays with the bundle class: the configuration, the `#[AsLlmsTxtLinkProvider]`
attribute (add a `LinkProviderListener` to the dispatcher instead), the routes of `routes.php`, the cache warmers and
Twig's escaping of the `*.md.twig` templates.

## Quick start

```yaml
# config/packages/llms_txt.yaml
llms_txt:
    title: 'SILARHI'
    summary: 'Web and mobile applications, from design to maintenance.'
```

```php
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;

#[Route('/contact', name: 'contact', options: ['llms_txt' => new LlmsTxtEntry(title: 'Contact')])]
public function contact(): Response
```

`GET /llms.txt` now lists the contact page. Add the pages of your database from
[a link provider](https://llms-txt-bundle.silarhi.dev/guides/link-providers/), or dump the file with
`php bin/console llms-txt:dump`.

## Documentation

Everything else is on **[llms-txt-bundle.silarhi.dev](https://llms-txt-bundle.silarhi.dev)**:

- [Installation](https://llms-txt-bundle.silarhi.dev/getting-started/installation/) and [configuration reference](https://llms-txt-bundle.silarhi.dev/getting-started/configuration/)
- Adding links: [link providers](https://llms-txt-bundle.silarhi.dev/guides/link-providers/), [event listeners](https://llms-txt-bundle.silarhi.dev/guides/event-listener/), [generators](https://llms-txt-bundle.silarhi.dev/guides/generators/) for large sections, [route options](https://llms-txt-bundle.silarhi.dev/guides/route-options/)
- [llms-full.txt](https://llms-txt-bundle.silarhi.dev/guides/llms-full/) and [Markdown versions](https://llms-txt-bundle.silarhi.dev/guides/markdown-versions/) of the pages
- [Serving the file](https://llms-txt-bundle.silarhi.dev/guides/serving/), on the fly or dumped, and [checking it](https://llms-txt-bundle.silarhi.dev/guides/debug/)
- [Discovery](https://llms-txt-bundle.silarhi.dev/guides/discovery/): `<link>` tag and `Link` header

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Release notes are in [CHANGELOG.md](CHANGELOG.md).

## License

Released under the [MIT License](LICENSE).
