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

namespace Silarhi\LlmsTxtBundle\Tests\Fixtures;

use function dirname;

use Override;
use Silarhi\LlmsTxtBundle\LlmsTxtBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Framework + Twig + the bundle, with the discovery header on and the file dumped into the cache directory.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    #[Override]
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new LlmsTxtBundle(),
        ];
    }

    #[Override]
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/llms-txt-bundle/cache/' . $this->getEnvironment();
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/llms-txt-bundle/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => [
                'utf8' => true,
                'default_uri' => 'https://example.com',
            ],
        ]);

        $container->extension('twig', [
            'default_path' => '%kernel.project_dir%/tests/Fixtures/templates',
            'strict_variables' => true,
        ]);

        $container->extension('llms_txt', [
            'title' => 'Example',
            'summary' => 'An example site.',
            'dump_directory' => '%kernel.cache_dir%/llms_txt',
            'discovery' => ['link_header' => true],
        ]);

        $services = $container->services();
        $services->set(PopulateSubscriber::class)->autowire()->autoconfigure();
        $services->set(PageController::class)->autowire()->autoconfigure()->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LlmsTxtBundle/config/routes.php');

        $routes->add('contact', '/contact')
            ->controller([PageController::class, 'html'])
            ->options(['llms_txt' => ['title' => 'Contact', 'description' => 'How to reach us']]);
        $routes->add('legal', '/legal')
            ->controller([PageController::class, 'html'])
            ->options(['llms_txt' => ['title' => 'Legal notice', 'section' => 'Optional']]);
        $routes->add('json', '/json')
            ->controller([PageController::class, 'json']);
    }
}
