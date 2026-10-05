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

namespace Silarhi\LlmsTxtBundle;

use Override;
use Silarhi\LlmsTxtBundle\Command\DumpCommand;
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\DependencyInjection\WebLinkPass;
use Silarhi\LlmsTxtBundle\EventListener\DiscoveryLinkListener;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\Service\Dumper;
use Silarhi\LlmsTxtBundle\Service\DumperInterface;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\GeneratorInterface;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Silarhi\LlmsTxtBundle\Service\RendererInterface;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Silarhi\LlmsTxtBundle\Twig\LlmsTxtExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;

final class LlmsTxtBundle extends AbstractBundle
{
    public const FILENAME = 'llms.txt';

    public const ROUTE = 'llms_txt';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WebLinkPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('title')
                    ->defaultNull()
                    ->info('The H1 of the file, the name of the site. Required, unless a LlmsTxtPopulateEvent listener sets it.')
                ->end()
                ->scalarNode('summary')
                    ->defaultNull()
                    ->info('A short summary of the site, rendered as a blockquote.')
                ->end()
                ->scalarNode('details')
                    ->defaultNull()
                    ->info('Free Markdown rendered after the summary: paragraphs, lists, anything but headings.')
                ->end()
                ->scalarNode('route_section')
                    ->defaultValue('Pages')
                    ->cannotBeEmpty()
                    ->info('The section of the routes carrying an "llms_txt" option without a "section" of their own.')
                ->end()
                ->scalarNode('dump_directory')
                    ->defaultValue('%kernel.project_dir%/public')
                    ->cannotBeEmpty()
                    ->info('Where llms-txt:dump writes the file, and where the controller looks for it before building it on the fly.')
                ->end()
                ->scalarNode('content_type')
                    ->defaultValue('text/plain; charset=UTF-8')
                    ->cannotBeEmpty()
                    ->info('The Content-Type served by the controller. "text/markdown" is more accurate, but browsers download it instead of showing it.')
                ->end()
                ->integerNode('max_age')
                    ->defaultValue(3600)
                    ->min(0)
                    ->info('The Cache-Control max-age of the controller responses, in seconds.')
                ->end()
                ->arrayNode('discovery')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('link_header')
                            ->defaultFalse()
                            ->info('Adds the file to the Link header of the HTML pages, through WebLink ("framework.web_link").')
                        ->end()
                        ->scalarNode('rel')
                            ->defaultValue('llms-txt')
                            ->cannotBeEmpty()
                            ->info('The relation of the Link header and of the llms_txt_link() tag.')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{
         *     title: string|null,
         *     summary: string|null,
         *     details: string|null,
         *     route_section: string,
         *     dump_directory: string,
         *     content_type: string,
         *     max_age: int,
         *     discovery: array{link_header: bool, rel: string},
         * } $config */
        $services = $container->services();

        $services->set('llms_txt.generator', Generator::class)
            ->args([service('event_dispatcher'), $config['title'], $config['summary'], $config['details']]);
        $services->alias(GeneratorInterface::class, 'llms_txt.generator');

        $services->set('llms_txt.renderer', MarkdownRenderer::class);
        $services->alias(RendererInterface::class, 'llms_txt.renderer');

        $services->set('llms_txt.dumper', Dumper::class)
            ->args([service('llms_txt.generator'), service('llms_txt.renderer'), service('filesystem'), $config['dump_directory']]);
        $services->alias(DumperInterface::class, 'llms_txt.dumper');

        $services->set('llms_txt.url_generator', UrlGenerator::class)
            ->args([service('router'), service('url_helper')]);

        $services->set('llms_txt.controller', LlmsTxtController::class)
            ->args([
                service('llms_txt.generator'),
                service('llms_txt.renderer'),
                $config['dump_directory'],
                $config['content_type'],
                $config['max_age'],
            ])
            ->tag('controller.service_arguments')
            ->public();

        $services->set('llms_txt.route_options_listener', RouteOptionsListener::class)
            ->args([service('router'), $config['route_section']])
            ->tag('kernel.event_subscriber');

        if ($config['discovery']['link_header']) {
            $services->set('llms_txt.discovery_link_listener', DiscoveryLinkListener::class)
                ->args([service('llms_txt.url_generator'), $config['discovery']['rel']])
                ->tag('kernel.event_subscriber');
        }

        if (class_exists(Command::class)) {
            $services->set('llms_txt.dump_command', DumpCommand::class)
                ->args([service('llms_txt.dumper'), service('router')])
                ->tag('console.command');
        }

        if (class_exists(Environment::class)) {
            $services->set('llms_txt.twig_extension', LlmsTxtExtension::class)
                ->args([service('llms_txt.url_generator'), $config['discovery']['rel']])
                ->tag('twig.extension');
        }
    }
}
