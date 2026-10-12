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

namespace Silarhi\LlmsTxtBundle\Tests\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\DependencyInjection\WebLinkPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WebLinkPassTest extends TestCase
{
    public function testNeedsWebLinkForTheDiscoveryListener(): void
    {
        $container = new ContainerBuilder();
        $container->register('llms_txt.discovery_link_listener');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('enable "framework.web_link"');

        (new WebLinkPass())->process($container);
    }

    public function testAcceptsTheDiscoveryListenerWithWebLink(): void
    {
        $container = new ContainerBuilder();
        $container->register('llms_txt.discovery_link_listener');
        $container->register('web_link.add_link_header_listener');

        (new WebLinkPass())->process($container);

        $this->expectNotToPerformAssertions();
    }
}
