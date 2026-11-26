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

use Twig\FileExtensionEscapingStrategy;

/**
 * Twig's "name" escaping strategy, without escaping the Markdown templates (*.md.twig), as Twig does for *.txt.twig:
 * it escapes HTML in any template whose extension it does not know, "Q&A" would end as "Q&amp;A" in the Markdown.
 * Set by MarkdownEscapingPass with "llms_txt.markdown_versions", unless the application has a strategy of its own.
 */
final class MarkdownEscapingStrategy
{
    public static function guess(string $name): string|false
    {
        if (str_ends_with($name, '.md.twig')) {
            return false;
        }

        return FileExtensionEscapingStrategy::guess($name);
    }
}
