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

namespace Silarhi\LlmsTxtBundle\Debug;

/**
 * What LinkChecker found while reading the links once.
 *
 * @internal
 */
final readonly class LinkCheck
{
    /**
     * @param list<SectionStats> $sections in the order of the file
     * @param int                $pages    the links with a content, for llms-full.txt
     * @param list<string>       $warnings
     */
    public function __construct(
        public array $sections,
        public int $links,
        public int $pages,
        public array $warnings,
    ) {
    }
}
