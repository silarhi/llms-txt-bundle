---
title: Installation
description: Install and register the LLMs.txt Bundle in a Symfony application.
sidebar:
    order: 1
---

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
