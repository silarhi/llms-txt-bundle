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

use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class LlmsTxtTest extends WebTestCase
{
    private const EXPECTED = <<<'MD'
        # Example

        > An example site.

        ## Pages

        - [Contact](https://example.com/contact): How to reach us
        - [Guide](https://example.com/guide.md)
        - [About](https://example.com/about)

        ## Offers

        - [First offer](https://example.com/contact?offer=first): The first one
        - [Second offer](https://example.com/offers/second)

        ## Blog

        - [Blog](https://example.com/blog): The latest posts

        ## Optional

        - [Legal notice](https://example.com/legal)

        MD;

    private const EXPECTED_FULL = <<<'MD'
        # Example

        > An example site.

        # Second offer

        Source: https://example.com/offers/second

        Half price,
        this week.

        # Blog

        Source: https://example.com/blog

        ## Posts

        MD;

    #[Override]
    protected function setUp(): void
    {
        (new Filesystem())->remove(self::bootKernel()->getCacheDir() . '/llms_txt');
        self::ensureKernelShutdown();
    }

    public function testServesTheFileOnTheFly(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/llms.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertSame(self::EXPECTED, $client->getInternalResponse()->getContent());
    }

    public function testServesTheFullFileOnTheFly(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/llms-full.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertSame(self::EXPECTED_FULL, $client->getInternalResponse()->getContent());
    }

    public function testDumpsTheFileThenServesIt(): void
    {
        $client = self::createClient();
        $kernel = $client->getKernel();

        $tester = new CommandTester((new Application($kernel))->find('llms-txt:dump'));
        $tester->execute(['--base-url' => 'https://www.example.org/']);
        $tester->assertCommandIsSuccessful();

        $path = $kernel->getCacheDir() . '/llms_txt/llms.txt';
        self::assertStringContainsString('- [Contact](https://www.example.org/contact)', (string) file_get_contents($path));

        $client->request('GET', 'https://example.com/llms.txt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('https://www.example.org/contact', $client->getInternalResponse()->getContent());

        self::assertFileExists($kernel->getCacheDir() . '/llms_txt/llms-full.txt');
    }

    public function testServesTheMarkdownVersionAtItsPath(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/guide.md');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertResponseHeaderSame('Vary', 'Accept');
        self::assertResponseNotHasHeader('Link');
        self::assertSame("# Guide & FAQ\n", $client->getInternalResponse()->getContent());
    }

    public function testServesTheMarkdownVersionToAnAcceptHeaderPreferringIt(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/guide', server: ['HTTP_ACCEPT' => 'text/markdown, text/html;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertSame("# Guide & FAQ\n", $client->getInternalResponse()->getContent());
    }

    public function testLinksTheHtmlPageToItsMarkdownVersion(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/guide', server: ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertResponseHeaderSame('Vary', 'Accept');
        self::assertStringContainsString('<https://example.com/guide.md>; rel="alternate"; type="text/markdown"', (string) $client->getResponse()->headers->get('Link'));
    }

    public function testLinksAPageWithoutFormatInItsPathToItself(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/faq');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<https://example.com/faq>; rel="alternate"; type="text/markdown"', (string) $client->getResponse()->headers->get('Link'));

        $client->request('GET', 'https://example.com/faq', server: ['HTTP_ACCEPT' => 'text/markdown']);
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertSame("# Guide & FAQ\n", $client->getInternalResponse()->getContent());
    }

    public function testAdvertisesTheFileOnHtmlPages(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/contact');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Link', '<https://example.com/llms.txt>; rel="llms-txt"');
        self::assertSelectorExists('head link[rel="llms-txt"][href="https://example.com/llms.txt"]');
        self::assertSelectorTextSame('body', 'https://example.com/llms.txt');
    }

    public function testDoesNotAdvertiseTheFileOnJson(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://example.com/json');

        self::assertResponseIsSuccessful();
        self::assertResponseNotHasHeader('Link');
    }
}
