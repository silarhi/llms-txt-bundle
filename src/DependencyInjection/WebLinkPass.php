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

namespace Silarhi\LlmsTxtBundle\DependencyInjection;

use LogicException;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The discovery header is written by the listener of WebLink: without it, the link would silently go nowhere.
 */
final class WebLinkPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition('llms_txt.discovery_link_listener') && !$container->has('web_link.add_link_header_listener')) {
            throw new LogicException('The "llms_txt.discovery.link_header" option needs WebLink: enable "framework.web_link".');
        }
    }
}
