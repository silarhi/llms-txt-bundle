---
title: llms-full.txt
description: Serve the Markdown of your pages in llms-full.txt, next to llms.txt.
sidebar:
    order: 4
---

`llms.txt` lists the pages; `llms-full.txt` holds their content, for the agents that read the whole site at once. It has
no formal spec: the bundle follows the files in the wild, the header of `llms.txt` then each page under its title and
its source URL.

```yaml
# config/packages/llms_txt.yaml
llms_txt:
    full: true # serves /llms-full.txt (404 otherwise), and llms-txt:dump writes it
```

## The content of a page

The `content` of a `Link` is the Markdown of its page. It is only rendered in `llms-full.txt`: `llms.txt` ignores it.

```php
#[AsLlmsTxtLinkProvider(section: 'Blog')]
public function posts(): iterable
{
    foreach ($this->postRepository->findPublished() as $post) {
        yield new Link(
            $this->urlGenerator->generate('post_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            $post->getTitle(),
            $post->getSummary(),
            // a closure: only called when llms-full.txt is rendered, not for llms.txt
            content: fn (): string => $this->twig->render('post/show.md.twig', ['post' => $post]),
        );
    }
}
```

`content` takes:

- **a string**, the Markdown of the page;
- **a closure returning a string**: the content is only built when `llms-full.txt` is, so a request of `llms.txt` does
  not render every page;
- **a closure returning an iterable of strings**, yielded piece by piece: a long page is never held whole.

With a generator of links, a string content does not pile up either: each link, and its content, is released once its
page is written. The closure only saves the work of building the content when `llms.txt` is rendered.

## The file

```md
# Example

> An example site.

# First post

Source: https://example.com/blog/first-post

The Markdown of the page…

# Second post

Source: https://example.com/blog/second-post

…
```

The pages come in the order of the sections, `Optional` last. A link without a content, or with an empty one, is left
out. The content is written as it is: its own headings stay, a string is only trimmed.

The file is built, cached and dumped like `llms.txt`: rendered whole before the response, with an `ETag`, served from
the dump directory when [dumped](/guides/serving/#dumped).
