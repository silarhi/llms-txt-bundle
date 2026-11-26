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

use JsonSerializable;
use Override;

/**
 * What debug:llms-txt shows, and prints with --format=json.
 *
 * @internal
 */
final readonly class Report implements JsonSerializable
{
    /**
     * @param list<Source>       $sources  in the order they run
     * @param list<SectionStats> $sections in the order of the file
     * @param int                $pages    the links with a content, for llms-full.txt
     * @param int|null           $size     the size of llms.txt, null without a title
     * @param int|null           $fullSize the size of llms-full.txt, null when disabled or without a title
     * @param list<DumpedFile>   $files
     * @param list<string>       $warnings
     */
    public function __construct(
        public array $sources,
        public array $sections,
        public int $links,
        public int $pages,
        public ?int $size,
        public ?int $fullSize,
        public array $files,
        public array $warnings,
    ) {
    }

    /**
     * @return array{sources: list<Source>, sections: list<SectionStats>, links: int, pages: int, size: int|null, full_size: int|null, files: list<DumpedFile>, warnings: list<string>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'sources' => $this->sources,
            'sections' => $this->sections,
            'links' => $this->links,
            'pages' => $this->pages,
            'size' => $this->size,
            'full_size' => $this->fullSize,
            'files' => $this->files,
            'warnings' => $this->warnings,
        ];
    }
}
