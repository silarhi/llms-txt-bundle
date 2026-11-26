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

namespace Silarhi\LlmsTxtBundle\Tests\Debug;

use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Debug\DumpedFile;
use Silarhi\LlmsTxtBundle\Debug\Inspector;
use Silarhi\LlmsTxtBundle\Debug\LinkChecker;
use Silarhi\LlmsTxtBundle\Debug\SourceDescriber;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Service\FullMarkdownRenderer;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;

use function strlen;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class InspectorTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/llms-txt-bundle/inspector-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testMeasuresTheFiles(): void
    {
        $report = $this->createInspector('Example', full: true)->inspect(1024);

        self::assertSame(strlen("# Example\n\n## Blog\n\n- [Blog](https://example.com/blog)\n"), $report->size);
        self::assertSame(strlen("# Example\n\n# Blog\n\nSource: https://example.com/blog\n\nPosts\n"), $report->fullSize);
        self::assertSame(1, $report->links);
        self::assertSame(1, $report->pages);
        self::assertSame([], $report->warnings);
    }

    public function testWarnsAboutTheSizeAndADisabledFullFile(): void
    {
        $report = $this->createInspector('Example', full: false)->inspect(10);

        self::assertNull($report->fullSize);
        self::assertSame([
            'llms.txt weighs 55 B, over the 10 B of --max-size: move links to the "Optional" section, or their pages to llms-full.txt.',
            '1 links have a content, but llms-full.txt is disabled: set "llms_txt.full.enabled".',
        ], $report->warnings);
    }

    public function testReportsAMissingTitle(): void
    {
        $report = $this->createInspector(null, full: false)->inspect(1024);

        self::assertNull($report->size);
        self::assertContains('The document has no title: set "llms_txt.title", or call Document::setTitle() from a listener.', $report->warnings);
    }

    public function testDescribesTheDumpedFiles(): void
    {
        (new Filesystem())->dumpFile($this->directory . '/llms.txt', "# Dumped\n");

        $files = $this->createInspector('Example', full: true)->inspect(1024)->files;

        self::assertCount(2, $files);
        self::assertSame($this->directory . '/llms.txt', $files[0]->path);
        self::assertSame(9, $files[0]->size);
        self::assertNotNull($files[0]->modified);
        self::assertEquals(new DumpedFile($this->directory . '/llms-full.txt', null, null), $files[1]);
    }

    private function createInspector(?string $title, bool $full): Inspector
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLinks('Blog', [new Link('https://example.com/blog', 'Blog', content: 'Posts')]);
        });

        return new Inspector(
            new Generator($dispatcher, $title),
            new MarkdownRenderer(),
            $full ? new FullMarkdownRenderer() : null,
            new SourceDescriber($dispatcher),
            new LinkChecker(new UrlGenerator(new RouteCollection(), new RequestContext(host: 'example.com', scheme: 'https'))),
            $this->directory,
        );
    }
}
