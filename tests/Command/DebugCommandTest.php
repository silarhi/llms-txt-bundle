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

namespace Silarhi\LlmsTxtBundle\Tests\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Command\DebugCommand;
use Silarhi\LlmsTxtBundle\Debug\Inspector;
use Silarhi\LlmsTxtBundle\Debug\LinkChecker;
use Silarhi\LlmsTxtBundle\Debug\SourceDescriber;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\EventListener\LinkProviderListener;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Service\FullMarkdownRenderer;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;

use function strlen;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class DebugCommandTest extends TestCase
{
    public function testFindsTheProblems(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()
                ->addLink('Pages', 'https://example.com/a', 'A')
                ->addLink('Pages', 'https://example.com/a', 'A again')
                ->addLink('Pages', '/relative', 'Relative')
                ->addLink('Pages', 'https://other.example/b', ' ')
                ->addLinks('Empty', []);
        });
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, new LinkProviderListener(
            static fn (): array => [new Link('https://example.com/blog', 'Blog', content: 'Posts')],
            'Blog',
            'app.blog::__invoke',
        ), -10);

        $tester = $this->createTester($dispatcher, withFull: false);
        $status = $tester->execute(['--strict' => true, '--max-size' => '100']);

        self::assertSame(Command::FAILURE, $status);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '';
        self::assertStringContainsString('-10 provider app.blog::__invoke Blog', $display);
        self::assertStringContainsString('0 listener Closure', $display);
        self::assertStringContainsString('1 links repeat the URL of another one, the first: "https://example.com/a".', $display);
        self::assertStringContainsString('over the 100 B of --max-size', $display);
        self::assertStringContainsString('problems found', $display);
    }

    public function testReportsInJson(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLink('Blog', 'https://example.com/blog', 'Blog');
        });

        $tester = $this->createTester($dispatcher, withFull: true);
        $tester->execute(['--format' => 'json', '--strict' => true]);

        $tester->assertCommandIsSuccessful();
        $report = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($report);
        self::assertSame([['name' => 'Blog', 'links' => 1]], $report['sections']);
        self::assertSame(1, $report['links']);
        self::assertSame(strlen("# Example\n\n## Blog\n\n- [Blog](https://example.com/blog)\n"), $report['size']);
        self::assertSame(strlen("# Example\n"), $report['full_size']);
        self::assertSame([], $report['warnings']);
        self::assertSame([['priority' => 0, 'kind' => 'listener', 'name' => 'Closure', 'section' => null]], $report['sources']);
        self::assertIsArray($report['files']);
        self::assertCount(2, $report['files']);
    }

    public function testSucceedsWithoutAProblem(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLink('Blog', 'https://example.com/blog', 'Blog');
        });

        $tester = $this->createTester($dispatcher, withFull: false);
        $tester->execute(['--strict' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('No problem found.', $tester->getDisplay());
    }

    public function testRejectsAnUnknownFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The format must be "txt" or "json", "string" given.');

        $this->createTester(new EventDispatcher(), withFull: false)->execute(['--format' => 'xml']);
    }

    public function testRejectsAnInvalidMaxSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The --max-size option must be a number of bytes.');

        $this->createTester(new EventDispatcher(), withFull: false)->execute(['--max-size' => '1M']);
    }

    private function createTester(EventDispatcher $dispatcher, bool $withFull): CommandTester
    {
        return new CommandTester(new DebugCommand(new Inspector(
            new Generator($dispatcher, 'Example'),
            new MarkdownRenderer(),
            $withFull ? new FullMarkdownRenderer() : null,
            new SourceDescriber($dispatcher),
            new LinkChecker(new UrlGenerator(new RouteCollection(), new RequestContext(host: 'example.com', scheme: 'https'))),
            sys_get_temp_dir() . '/llms-txt-bundle/debug-' . bin2hex(random_bytes(4)),
        )));
    }
}
