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

use Override;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Model\Document;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class Generator implements GeneratorInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ?string $title = null,
        private ?string $summary = null,
        private ?string $details = null,
    ) {
    }

    #[Override]
    public function generate(): Document
    {
        $document = new Document($this->title, $this->summary, $this->details);
        $this->dispatcher->dispatch(new LlmsTxtPopulateEvent($document));

        return $document;
    }
}
