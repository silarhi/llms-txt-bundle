---
title: Installation
description: Install and register the LLMs.txt Bundle in a Symfony application.
sidebar:
    order: 1
---

## Requirements

- PHP 8.2+
- Symfony 6.4, 7.x or 8.x
- `symfony/framework-bundle` to register the bundle: the package does not install it, and the generator, the renderers
  and the dumpers work without it, see [Without the bundle](#without-the-bundle)
- `symfony/console` for the `llms-txt:dump` command, `symfony/twig-bundle` for the Twig functions, `symfony/web-link`
  for the `Link` header of `discovery.link_header`

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

## Without the bundle

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
