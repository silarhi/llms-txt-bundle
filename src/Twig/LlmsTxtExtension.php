<?php

declare(strict_types=1);

/*
 * This file is part of the LLMs.txt Bundle package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\LlmsTxtBundle\Twig;

use Override;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;

use function sprintf;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ llms_txt_url() }} and {{ llms_txt_link() }}, the <link> tag advertising the file in the <head> of the pages.
 */
final class LlmsTxtExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGenerator $urlGenerator,
        private readonly string $rel,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('llms_txt_url', $this->urlGenerator->generate(...)),
            new TwigFunction('llms_txt_link', $this->renderLink(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, string> $attributes extra or overriding attributes, e.g. {type: 'text/markdown', title: 'LLMs.txt'}
     */
    public function renderLink(array $attributes = []): string
    {
        $attributes = ['rel' => $this->rel, 'href' => $this->urlGenerator->generate(), ...$attributes];

        $html = '<link';
        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', htmlspecialchars($name, \ENT_QUOTES), htmlspecialchars($value, \ENT_QUOTES));
        }

        return $html . '>';
    }
}
