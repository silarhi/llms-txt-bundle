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

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives DefaultUriContext the "framework.router.default_uri" the router's request context is built from: the first
 * argument of "router.request_context". FrameworkBundle 6.4.0 writes it there, later versions pass it through the
 * "router.request_context.base_url" parameter.
 */
final class DefaultUriPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('llms_txt.default_uri_context') || !$container->hasDefinition('router.request_context')) {
            return;
        }

        $arguments = $container->getDefinition('router.request_context')->getArguments();
        $container->getDefinition('llms_txt.default_uri_context')->replaceArgument(2, $arguments[0] ?? '');
    }
}
