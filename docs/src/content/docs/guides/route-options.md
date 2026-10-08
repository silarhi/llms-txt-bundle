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
