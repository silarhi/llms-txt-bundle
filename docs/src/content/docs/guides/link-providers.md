---
title: From a link provider
description: Add the links of a section with the AsLlmsTxtLinkProvider attribute.
sidebar:
    order: 1
---

A link provider is a method returning the links of one section. The `#[AsLlmsTxtLinkProvider]` attribute registers it,
the way `#[AsEventListener]` registers a listener:

```php
use Silarhi\LlmsTxtBundle\Attribute\AsLlmsTxtLinkProvider;
use Silarhi\LlmsTxtBundle\Model\Link;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CatalogLinks
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @return iterable<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Products')]
    public function products(): iterable
    {
        foreach ($this->productRepository->findBy(['published' => true]) as $product) {
            yield new Link(
                $this->urlGenerator->generate('product_show', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                $product->getName(),
                $product->getSummary(), // optional description
            );
        }
    }

    /**
     * @return iterable<Link>
     */
    #[AsLlmsTxtLinkProvider(section: 'Categories', priority: 10)]
    public function categories(): iterable
    {
        // …
    }
}
```

With autoconfiguration on (the default of `config/services.yaml`), there is nothing else to declare. A service with a
single provider can carry the attribute on its class instead: the provider is then its `__invoke()` method, or the
one `method` names.

```php
#[AsLlmsTxtLinkProvider(section: 'Blog')]
final readonly class BlogLinks
{
    /**
     * @return iterable<Link>
     */
    public function __invoke(): iterable
    {
        // …
    }
}
```

## Options

| Option     | Default      | Description                                                                  |
| ---------- | ------------ | ---------------------------------------------------------------------------- |
| `section`  | _(required)_ | The H2 section of the links.                                                 |
| `method`   | `__invoke`   | On a class, the method returning the links. Ignored on a method.             |
| `priority` | `0`          | The priority of the `LlmsTxtPopulateEvent` listener that calls the provider. |

The method takes no argument and returns an iterable of `Link`: an array, or a generator to
[keep the memory flat](/guides/generators/). It is called each time the file is built, and a generator is only read
while the file is rendered.

## Order of the sections

Sections keep the order of their first use, `Optional` always last. Each provider is called by its own
`LlmsTxtPopulateEvent` listener, with the provider's `priority`: providers and
[event listeners](/guides/event-listener/) share one order, highest priority first. The
[route options](/guides/route-options/) run at `128`, before both.

## Without the attribute

The attribute adds the `llms_txt.link_provider` tag, which a service can also carry by hand:

```yaml
# config/services.yaml
services:
    App\Llms\CatalogLinks:
        tags:
            - { name: llms_txt.link_provider, section: Products, method: products }
            - { name: llms_txt.link_provider, section: Categories, method: categories, priority: 10 }
```

An invalid tag (no `section`, a method that is not public) fails when the container builds, a provider returning
anything but an iterable fails the requests of the file, both with an `InvalidLinkProviderException`.

:::tip
A provider is the shortest way to add a section. Reach for an [event listener](/guides/event-listener/) to change the
document itself: its title, summary or details.
:::
