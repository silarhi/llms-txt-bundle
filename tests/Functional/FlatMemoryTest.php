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

namespace Silarhi\LlmsTxtBundle\Tests\Functional;

use Generator;
use Override;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Controller\LlmsTxtController;
use Silarhi\LlmsTxtBundle\Event\LlmsTxtPopulateEvent;
use Silarhi\LlmsTxtBundle\Model\Link;
use Silarhi\LlmsTxtBundle\Service\Dumper;
use Silarhi\LlmsTxtBundle\Service\Generator as LlmsTxtGenerator;
use Silarhi\LlmsTxtBundle\Service\MarkdownRenderer;

use function sprintf;
use function strlen;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Links added as generators are read one at a time, from the listener to the file or the response: the memory stays
 * flat whatever their number.
 */
final class FlatMemoryTest extends TestCase
{
    private const LINKS = 200_000;

    // the file weighs about 15 MB: holding it, or its links, would take far more
    private const MAX_GROWTH = 2 * 1024 * 1024;

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/llms-txt-bundle/memory-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testDumpsWithAFlatMemory(): void
    {
        $dumper = new Dumper($this->createGenerator(), new MarkdownRenderer(), new Filesystem(), $this->directory);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $path = $dumper->dump();
        $growth = memory_get_peak_usage() - $before;

        self::assertGreaterThan(10 * 1024 * 1024, filesize($path));
        self::assertLessThan(self::MAX_GROWTH, $growth, sprintf('The dump took %.1f MB', $growth / 1024 / 1024));
    }

    public function testStreamsWithAFlatMemory(): void
    {
        $controller = new LlmsTxtController($this->createGenerator(), new MarkdownRenderer(), $this->directory, 'text/plain', 0);

        $bytes = 0;
        memory_reset_peak_usage();
        $before = memory_get_usage();
        // what a web server does with the output: sends it on, chunk by chunk
        ob_start(static function (string $buffer) use (&$bytes): string {
            $bytes += strlen($buffer);

            return '';
        }, 8192);
        try {
            $controller(new Request())->sendContent();
        } finally {
            ob_end_clean();
        }
        $growth = memory_get_peak_usage() - $before;

        self::assertGreaterThan(10 * 1024 * 1024, $bytes);
        self::assertLessThan(self::MAX_GROWTH, $growth, sprintf('The response took %.1f MB', $growth / 1024 / 1024));
    }

    private function createGenerator(): LlmsTxtGenerator
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(LlmsTxtPopulateEvent::class, static function (LlmsTxtPopulateEvent $event): void {
            $event->getDocument()->addLinks('Offers', (static function (): Generator {
                for ($i = 0; $i < self::LINKS; ++$i) {
                    yield new Link('https://example.com/offers/' . $i, 'Offer ' . $i, 'The description of the offer ' . $i);
                }
            })());
        });

        return new LlmsTxtGenerator($dispatcher, 'Example');
    }
}
