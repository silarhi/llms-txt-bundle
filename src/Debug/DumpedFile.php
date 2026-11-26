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
 * A file llms-txt:dump writes, dumped or not.
 *
 * @internal
 */
final readonly class DumpedFile implements JsonSerializable
{
    /**
     * @param int|null    $size     null when the file is not dumped
     * @param string|null $modified the modification date, DATE_ATOM
     */
    public function __construct(
        public string $path,
        public ?int $size,
        public ?string $modified,
    ) {
    }

    /**
     * @return array{path: string, size: int|null, modified: string|null}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['path' => $this->path, 'size' => $this->size, 'modified' => $this->modified];
    }
}
