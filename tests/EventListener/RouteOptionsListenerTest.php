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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\RouteOptionsListener;
use Silarhi\LlmsTxtBundle\Exception\InvalidRouteOptionException;
use Silarhi\LlmsTxtBundle\Model\Document;
use Silarhi\LlmsTxtBundle\Model\Link;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class RouteOptionsListenerTest extends TestCase
{
    public function testAddsTheRoutesCarryingTheOption(): void
    {
        $document = $this->populate([
            'home' => new Route('/'),
            'contact' => new Route('/contact', options: ['llms_txt' => ['title' => 'Contact', 'description' => 'Reach us']]),
            'legal' => new Route('/legal', options: ['llms_txt' => ['title' => 'Legal', 'section' => 'Optional']]),
            'hidden' => new Route('/hidden', options: ['llms_txt' => false]),
        ]);

        self::assertCount(2, $document->getSections());
        self::assertEquals([new Link('https://example.com/contact', 'Contact', 'Reach us')], iterator_to_array($document->section('Pages')->getLinks(), false));
        self::assertEquals([new Link('https://example.com/legal', 'Legal')], iterator_to_array($document->section('Optional')->getLinks(), false));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideInvalidOptions(): iterable
    {
        yield 'not an array' => [true, 'must be an array'];
        yield 'no title' => [['description' => 'Reach us'], 'needs a non-empty "title"'];
        yield 'empty title' => [['title' => ' '], 'needs a non-empty "title"'];
        yield 'unknown key' => [['title' => 'Contact', 'priority' => 1], 'unknown keys "priority"'];
        yield 'invalid description' => [['title' => 'Contact', 'description' => 1], '"description"'];
        yield 'invalid section' => [['title' => 'Contact', 'section' => ''], '"section"'];
    }

    #[DataProvider('provideInvalidOptions')]
    public function testRejectsInvalidOptions(mixed $options, string $message): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage($message);

        $this->populate(['contact' => new Route('/contact', options: ['llms_txt' => $options])]);
    }

    public function testRejectsRoutesNeedingParameters(): void
    {
        $this->expectException(InvalidRouteOptionException::class);
        $this->expectExceptionMessage('needs parameters');

        $this->populate(['offer' => new Route('/offers/{slug}', options: ['llms_txt' => ['title' => 'Offer']])]);
    }

    /**
     * @param array<string, Route> $routes
     */
    private function populate(array $routes): Document
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        $router = new Router(new ClosureLoader(), static fn (): RouteCollection => $collection, [], new RequestContext(host: 'example.com', scheme: 'https'));

        $document = new Document('Example');
        (new RouteOptionsListener($router, 'Pages'))->populate(new LlmsTxtPopulateEvent($document));

        return $document;
    }
}
