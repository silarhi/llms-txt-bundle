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

namespace Silarhi\LlmsTxtBundle\Tests\Model;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Model\Document;

final class DocumentTest extends TestCase
{
    public function testListenersCanReplaceTheHeader(): void
    {
        $document = (new Document('Configured'))
            ->setTitle('Example')
            ->setSummary('A summary')
            ->setDetails('Some details');

        self::assertSame('Example', $document->getTitle());
        self::assertSame('A summary', $document->getSummary());
        self::assertSame('Some details', $document->getDetails());
    }
}
