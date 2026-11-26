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

namespace Silarhi\LlmsTxtBundle\Tests\EventListener;

namespace Silarhi\LlmsTxtBundle\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Twig\MarkdownEscapingStrategy;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class MarkdownEscapingStrategyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|false}>
     */
    public static function provideNames(): iterable
    {
        yield 'Markdown' => ['post/show.md.twig', false];
        yield 'HTML' => ['post/show.html.twig', 'html'];
        yield 'text, as Twig does' => ['email.txt.twig', false];
        yield 'JavaScript, as Twig does' => ['app.js.twig', 'js'];
        yield 'no extension' => ['post/show.twig', 'html'];
    }

    #[DataProvider('provideNames')]
    public function testGuessesTheStrategy(string $name, string|false $strategy): void
    {
        self::assertSame($strategy, MarkdownEscapingStrategy::guess($name));
    }

    public function testRendersTheMarkdownTemplatesUnescaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            'page.md.twig' => '# {{ title }}',
            'page.html.twig' => '<h1>{{ title }}</h1>',
        ]), ['autoescape' => MarkdownEscapingStrategy::guess(...)]);

        self::assertSame('# Q&A', $twig->render('page.md.twig', ['title' => 'Q&A']));
        self::assertSame('<h1>Q&amp;A</h1>', $twig->render('page.html.twig', ['title' => 'Q&A']));
    }
}
