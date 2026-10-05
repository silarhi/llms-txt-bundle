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

namespace Silarhi\LlmsTxtBundle\Event;

use Silarhi\LlmsTxtBundle\Model\Document;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched each time the file is built, on the fly or by the dump command: its listeners add the links of the site.
 */
final class LlmsTxtPopulateEvent extends Event
{
    public function __construct(
        private readonly Document $document,
    ) {
    }

    public function getDocument(): Document
    {
        return $this->document;
    }
}
