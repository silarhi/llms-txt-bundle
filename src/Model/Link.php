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

namespace Silarhi\LlmsTxtBundle\Model;

use Closure;

/**
 * One "- [title](url): description" entry of a section, and the Markdown of its page for llms-full.txt.
 */
final readonly class Link
{
    /**
     * @param string|(Closure(): (string|iterable<string>))|null $content the Markdown of the page, rendered in llms-full.txt
     *                                                                    only: a closure is only called when that file
     *                                                                    is, and can yield the page piece by piece
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?string $description = null,
        public string|Closure|null $content = null,
    ) {
    }
}
