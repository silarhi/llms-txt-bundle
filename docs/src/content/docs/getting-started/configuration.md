---
title: Configuration
description: Every option of the llms_txt configuration, with its default value.
sidebar:
    order: 2
---

```yaml
# config/packages/llms_txt.yaml
llms_txt:
    # The H1 of the file, the name of the site (required, unless a listener sets it), e.g. 'SILARHI'
    title: null
    # A short summary, rendered as a blockquote, e.g. 'Agence de développement Web PHP à Toulouse.'
    summary: null
    # Free Markdown rendered after the summary: paragraphs, lists, anything but headings
    details: null
    # The section of the routes carrying an "llms_txt" option without one of their own
    route_section: 'Pages'
    # Where llms-txt:dump writes the file, and where the controller looks for it first
    dump_directory: '%kernel.project_dir%/public'
    # "text/markdown" is more accurate, but browsers download it instead of showing it
    content_type: 'text/plain; charset=UTF-8'
    # Cache-Control max-age of the controller responses, in seconds
    max_age: 3600
    cache:
        # The cache pool keeping the file built on the fly, one entry per base URL
        pool: 'cache.app'
        # How long the file built on the fly is cached, in seconds: 0 builds it on each request
        ttl: 300
    discovery:
        # Adds the file to the Link header of the HTML pages, through WebLink: needs symfony/web-link
        link_header: false
        # The relation of the Link header and of the llms_txt_link() tag
        rel: 'llms-txt'
```

Set `framework.router.default_uri` (or `framework.trusted_hosts`) in production: see
[the host of the URLs](/guides/serving/#the-host-of-the-urls).
