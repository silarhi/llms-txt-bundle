---
title: Markdown versions
description: Serve the Markdown version of a page at page.md, and to the agents asking for text/markdown.
sidebar:
    order: 6
---

An agent reads Markdown better than HTML. A route whose `_format` requirement lists `md` has a Markdown version,
rendered by its action from a template of its own: the bundle does not convert HTML.

```yaml
# config/packages/llms_txt.yaml
llms_txt:
    markdown_versions: true
```

```php
#[Route('/blog/{slug}.{_format}', name: 'post_show', format: 'html', requirements: ['_format' => 'html|md'])]
public function show(Request $request, Post $post): Response
{
    return $this->render('post/show.' . $request->getRequestFormat() . '.twig', ['post' => $post]);
}
```

```twig
{# templates/post/show.md.twig #}
# {{ post.title }}

{{ post.body }}
```

`format: 'html'` is the default `_format`. The bundle registers the `md` request format, served as
`text/markdown; charset=UTF-8`, and the action answers Markdown:

- **at `/blog/first-post.md`**: the `{_format}` of the path is `md`;
- **at `/blog/first-post`**, to an `Accept` header preferring `text/markdown` to `text/html`, e.g.
  `Accept: text/markdown, text/html;q=0.9`. A browser, which asks for `text/html` first, still gets the HTML page.

The requirement must name `md` as one of its alternatives (`html|md`, `(md|html)`): a pattern which merely matches it,
such as `[a-z]+`, does not opt in, its action would get the Markdown requests without a Markdown template.

## Accept-only pages

Without `{_format}` in the path, the `Accept` header is the only way to the Markdown version: the same route, without
the suffix.

```php
#[Route('/faq', name: 'faq', format: 'html', requirements: ['_format' => 'html|md'])]
```

The router ignores a requirement on a variable which is not in the path: it only tells the bundle that `/faq` has a
Markdown version.

## Templates

Twig escapes HTML in a template whose extension it does not know: `{{ post.title }}` would render `Q&A` as `Q&amp;A`.
With `markdown_versions`, the bundle escapes nothing in the `*.md.twig` templates, as Twig does for `*.txt.twig`. An
application with an escaping strategy of its own (`twig.autoescape_service`) keeps it. Clear the cache after enabling
the option: Twig decides the escaping when it compiles a template.

A content stored as HTML, e.g. by a WYSIWYG editor, can be converted in the template by the `html_to_markdown` filter
of [twig/markdown-extra](https://twig.symfony.com/doc/3.x/filters/html_to_markdown.html).

## Headers

- The responses of these routes carry `Vary: Accept`: one URL answers HTML or Markdown, a cache keeps both.
- The HTML page links to its Markdown version in its `Link` header, through
  [WebLink](https://symfony.com/doc/current/web_link.html) (needs `symfony/web-link`):
  `<https://example.com/blog/first-post.md>; rel="alternate"; type="text/markdown"`. An Accept-only page links to
  itself.

## In llms.txt

The link of a [route option](/guides/route-options/#markdown-versions) points to the `.md` URL on its own. In a
[link provider](/guides/link-providers/), generate the URL with `_format: md`:

```php
$this->urlGenerator->generate('post_show', ['slug' => $post->getSlug(), '_format' => 'md'], UrlGeneratorInterface::ABSOLUTE_URL);
```

## Caching

The bundle lists the routes with a Markdown version next to the compiled routes, in
`%kernel.build_dir%/llms_txt_markdown_routes.php`: a request checks its route against this list without loading the
route collection. The list follows the route files like the router cache, and `cache:warmup` builds it.
