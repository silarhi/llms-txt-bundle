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
    full:
        # Serves /llms-full.txt (404 otherwise) and dumps it with llms.txt: the pages whose links have a "content"
        enabled: false
    markdown_versions:
        # The routes whose "_format" requirement lists "md" answer Markdown to an Accept header preferring it,
        # and link to it. Registers the "md" format, and stops Twig from escaping HTML in *.md.twig
        enabled: false
    discovery:
        # Adds the file to the Link header of the HTML pages, through WebLink: needs symfony/web-link
        link_header: false
        # The relation of the Link header and of the llms_txt_link() tag
        rel: 'llms-txt'
```
