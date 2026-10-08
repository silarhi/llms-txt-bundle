---
title: From an event listener
description: Add links to llms.txt from a LlmsTxtPopulateEvent listener.
sidebar:
    order: 1
---

```php
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LlmsTxtListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ProjectRepository $projectRepository,
    ) {
    }

    #[AsEventListener]
    public function __invoke(LlmsTxtPopulateEvent $event): void
    {
        $document = $event->getDocument();

        foreach ($this->projectRepository->findBy(['published' => true]) as $project) {
            $document->addLink(
                'Projets',
                $this->urlGenerator->generate('project_show', ['slug' => $project->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                $project->getName(),
                $project->getSummary(), // optional description
            );
        }
    }
}
```

The document can also be changed as a whole: `setTitle()`, `setSummary()`, `setDetails()`, or `section('Projets')->addLink(new Link(...))`. Sections keep the order of their first use, except `Optional` ([its links can be skipped](https://llmstxt.org/#format) when a shorter context is needed), which always comes last.

:::tip
Keep the file consistent with your sitemap: add the pages you index, not the `noindex` ones.
:::
