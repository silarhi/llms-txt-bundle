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

namespace Silarhi\LlmsTxtBundle\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Attribute\Route;

final class LlmsTxtEntryTest extends TestCase
{
    public function testIsBuiltInARouteAttribute(): void
    {
        // the loader of the applications: the option survives the route loading as an object
        $route = (new AttributeRouteControllerLoader())->load(LlmsTxtEntryFixtureController::class)->get('cgu');

        self::assertNotNull($route);
        self::assertEquals(new LlmsTxtEntry('CGU', 'Terms of use', 'Optional'), $route->getOption('llms_txt'));
    }

    public function testIsBuiltFromAnArray(): void
    {
        self::assertEquals(new LlmsTxtEntry('Contact'), LlmsTxtEntry::fromArray(['title' => 'Contact']));
        self::assertEquals(
            new LlmsTxtEntry('CGU', 'Terms of use', 'Optional'),
            LlmsTxtEntry::fromArray(['title' => 'CGU', 'description' => 'Terms of use', 'section' => 'Optional']),
        );
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function provideInvalidArrays(): iterable
    {
        yield 'no title' => [['description' => 'Reach us'], 'needs a non-empty "title"'];
        yield 'empty title' => [['title' => ' '], 'needs a non-empty "title"'];
        yield 'title of the wrong type' => [['title' => 1], 'needs a non-empty "title"'];
        yield 'unknown key' => [['title' => 'Contact', 'priority' => 1], 'unknown keys "priority", allowed: "title", "description", "section"'];
        yield 'description of the wrong type' => [['title' => 'Contact', 'description' => 1], 'The "description" of an llms.txt entry must be a string, "int" given'];
        yield 'empty section' => [['title' => 'Contact', 'section' => ''], 'The "section" of an llms.txt entry must be a non-empty string'];
        yield 'section of the wrong type' => [['title' => 'Contact', 'section' => true], 'The "section" of an llms.txt entry must be a non-empty string, "bool" given'];
    }

    /**
     * @param array<mixed> $option
     */
    #[DataProvider('provideInvalidArrays')]
    public function testRejectsInvalidArrays(array $option, string $message): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage($message);

        LlmsTxtEntry::fromArray($option);
    }

    public function testRejectsAnEmptyTitle(): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage('needs a non-empty "title"');

        new LlmsTxtEntry("\n");
    }
}

/**
 * @internal
 */
final class LlmsTxtEntryFixtureController
{
    #[Route('/cgu', name: 'cgu', options: ['llms_txt' => new LlmsTxtEntry(title: 'CGU', description: 'Terms of use', section: 'Optional')])]
    public function cgu(): string
    {
        return 'CGU';
    }
}
