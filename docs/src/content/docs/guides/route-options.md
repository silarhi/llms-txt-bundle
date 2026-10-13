---
title: From route options
description: Add static pages to llms.txt with an llms_txt route option.
sidebar:
    order: 3
---

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

`section` defaults to `route_section`. Routes declared in YAML or XML take the same keys as an array:

```yaml
contact:
    path: /contact
    controller: App\Controller\ContactController
    options:
        llms_txt: { title: Contact, description: How to reach the team }
```

Only routes without mandatory parameters can carry the option: add the others from a listener.

## Invalid options

With `LlmsTxtEntry`, an empty title or section fails as soon as the routes load, `cache:warmup` included. The array
form is only checked when the file is generated: an empty title, an unknown key or a value of the wrong type fails the
requests of `/llms.txt` and `llms-txt:dump`, not the deployment.

## Excluding a route

A route without the option, or with `llms_txt: false`, stays out of the file: `false` overrides an option set on a
whole imported route collection.

## Caching

Reading the option means loading every route file of the application, which the router does not do in production. The
routes carrying it are cached in `%kernel.build_dir%/llms_txt_routes.php`, next to the compiled routes and invalidated
with them: in debug, changing a route file rebuilds it. `cache:warmup` writes it, so a read-only build directory works.
The URLs are still generated on each request, as they depend on the request context.
