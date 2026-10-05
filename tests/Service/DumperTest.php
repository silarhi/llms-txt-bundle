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

namespace Silarhi\LlmsTxtBundle\Tests\Service;

use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Exception\MissingTitleException;
use Silarhi\LlmsTxtBundle\Service\Dumper;
use Silarhi\LlmsTxtBundle\Service\Generator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

final class DumperTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/llms-txt-bundle/dumper-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testDumpsIntoTheConfiguredDirectory(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLink('Pages', 'https://example.com/contact', 'Contact');
        });

        $path = $this->createDumper($dispatcher, 'Example')->dump();

        self::assertSame($this->directory . '/llms.txt', $path);
        self::assertSame("# Example\n\n## Pages\n\n- [Contact](https://example.com/contact)\n", file_get_contents($path));
    }

    public function testDumpsIntoAnotherDirectory(): void
    {
        $path = $this->createDumper(new EventDispatcher(), 'Example')->dump($this->directory . '/other/');

        self::assertSame($this->directory . '/other/llms.txt', $path);
        self::assertFileExists($path);
    }

    public function testKeepsThePreviousFileWhenTheDocumentIsInvalid(): void
    {
        $this->createDumper(new EventDispatcher(), 'Example')->dump();

        try {
            $this->createDumper(new EventDispatcher(), null)->dump();
            self::fail('The dump should have failed');
        } catch (MissingTitleException) {
        }

        self::assertSame("# Example\n", file_get_contents($this->directory . '/llms.txt'));
    }

    private function createDumper(EventDispatcher $dispatcher, ?string $title): Dumper
    {
        return new Dumper(new Generator($dispatcher, $title), new MarkdownRenderer(), new Filesystem(), $this->directory);
    }
}
