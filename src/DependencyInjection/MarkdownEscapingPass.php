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

use function is_array;

use Override;
use Silarhi\LlmsTxtBundle\Twig\MarkdownEscapingStrategy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stops Twig from escaping HTML in the Markdown templates (*.md.twig) when "llms_txt.markdown_versions" is enabled.
 * TwigBundle sets the "name" strategy unless the application configures "twig.autoescape_service": an application
 * strategy is left as it is.
 */
final class MarkdownEscapingPass implements CompilerPassInterface
{
    /**
     * The service registered with "llms_txt.markdown_versions" only.
     */
    public const LISTENER = 'llms_txt.markdown_version_listener';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::LISTENER) || !$container->hasDefinition('twig')) {
            return;
        }

        $twig = $container->getDefinition('twig');
        $options = $twig->getArguments()[1] ?? null;
        if (!is_array($options) || 'name' !== ($options['autoescape'] ?? null)) {
            return;
        }

        $options['autoescape'] = [MarkdownEscapingStrategy::class, 'guess'];
        $twig->replaceArgument(1, $options);
    }
}
