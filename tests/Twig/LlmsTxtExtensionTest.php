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

namespace Silarhi\LlmsTxtBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Service\UrlGenerator;
use Silarhi\LlmsTxtBundle\Twig\LlmsTxtExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGenerator as RoutingUrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LlmsTxtExtensionTest extends TestCase
{
    public function testRendersTheUrlAndTheLink(): void
    {
        $twig = new Environment(new ArrayLoader([
            'url' => '{{ llms_txt_url() }}',
            'link' => '{{ llms_txt_link() }}',
            'custom' => '{{ llms_txt_link({rel: "alternate", type: "text/markdown", title: "LLMs \"txt\""}) }}',
        ]));
        $context = new RequestContext(host: 'example.com', scheme: 'https');
        $twig->addExtension(new LlmsTxtExtension(new UrlGenerator(new RoutingUrlGenerator(new RouteCollection(), $context), new UrlHelper(new RequestStack(), $context)), 'llms-txt'));

        self::assertSame('https://example.com/llms.txt', $twig->render('url'));
        self::assertSame('<link rel="llms-txt" href="https://example.com/llms.txt">', $twig->render('link'));
        self::assertSame('<link rel="alternate" href="https://example.com/llms.txt" type="text/markdown" title="LLMs &quot;txt&quot;">', $twig->render('custom'));
    }
}
