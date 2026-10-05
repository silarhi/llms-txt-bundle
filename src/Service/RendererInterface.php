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

namespace Silarhi\LlmsTxtBundle\Service;

use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Model\Document;

interface RendererInterface
{
    /**
     * The file, chunk by chunk: the links are read while the chunks are, not before.
     *
     * @return iterable<string>
     *
     * @throws MissingTitleException right away, before any chunk
     */
    public function render(Document $document): iterable;
}
