---
title: Discovery
description: Advertise llms.txt with a link tag or a Link header.
sidebar:
    order: 5
---

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
