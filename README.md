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

- **Event driven**: listeners of `LlmsTxtPopulateEvent` add the links, from your repositories, like `SitemapPopulateEvent`
- **Route options**: static pages join the file with an `llms_txt` route option, like the `sitemap` one
- **On the fly or dumped**: the controller serves the dumped file when there is one, and builds it otherwise
- **Flat memory**: links added as generators are read one at a time, streamed to the response or the dumped file
- **Spec compliant**: H1 title, blockquote summary, H2 sections of links, the `Optional` section always last, escaped links
- **Discovery**: a `<link>` tag for the `<head>` of your pages and an opt-in `Link` header, through [WebLink](https://symfony.com/doc/current/web_link.html)

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x

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

`GET /llms.txt` now lists the contact page. Set `framework.router.default_uri` (or `framework.trusted_hosts`) in
production: the URLs of the file come from it, not from the `Host` header of the request. Add the pages of your database from
[an event listener](https://llms-txt-bundle.silarhi.dev/guides/event-listener/), or dump the file with
`php bin/console llms-txt:dump`.

## Documentation

Everything else is on **[llms-txt-bundle.silarhi.dev](https://llms-txt-bundle.silarhi.dev)**:

- [Installation](https://llms-txt-bundle.silarhi.dev/getting-started/installation/) and [configuration reference](https://llms-txt-bundle.silarhi.dev/getting-started/configuration/)
- Adding links: [event listeners](https://llms-txt-bundle.silarhi.dev/guides/event-listener/), [generators](https://llms-txt-bundle.silarhi.dev/guides/generators/) for large sections, [route options](https://llms-txt-bundle.silarhi.dev/guides/route-options/)
- [Serving the file](https://llms-txt-bundle.silarhi.dev/guides/serving/), on the fly or dumped
- [Discovery](https://llms-txt-bundle.silarhi.dev/guides/discovery/): `<link>` tag and `Link` header

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Release notes are in [CHANGELOG.md](CHANGELOG.md).

## License

Released under the [MIT License](LICENSE).
