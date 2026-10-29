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
use Psr\Cache\CacheItemPoolInterface;
use Silarhi\LlmsTxtBundle\Tests\Fixtures\PopulateSubscriber;
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
        - [About](https://example.com/about)

        ## Offers

        - [First offer](https://example.com/contact?offer=first): The first one

        ## Optional

        - [Legal notice](https://example.com/legal)

        MD;

    #[Override]
    protected function setUp(): void
    {
        (new Filesystem())->remove(self::bootKernel()->getCacheDir() . '/llms_txt');
        // the file built on the fly is cached in cache.app, which outlives the kernel
        $cache = self::getContainer()->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
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

    public function testUsesTheDefaultUriWhateverTheHostOfTheRequest(): void
    {
        $client = self::createClient();
        $client->request('GET', 'https://forged.example.net/llms.txt');

        self::assertResponseIsSuccessful();
        self::assertSame(self::EXPECTED, $client->getInternalResponse()->getContent());

        $client->request('GET', 'https://forged.example.net/contact');
        self::assertResponseHeaderSame('Link', '<https://example.com/llms.txt>; rel="llms-txt"');
        self::assertSelectorExists('head link[rel="llms-txt"][href="https://example.com/llms.txt"]');
    }

    public function testServesTheFileFromTheCache(): void
    {
        $client = self::createClient();
        // one kernel for all the requests, to count the calls of its subscriber
        $client->disableReboot();
        $client->request('GET', 'https://example.com/llms.txt');
        $etag = $client->getInternalResponse()->getHeader('ETag');
        self::assertIsString($etag);

        $subscriber = self::getContainer()->get(PopulateSubscriber::class);
        self::assertInstanceOf(PopulateSubscriber::class, $subscriber);
        $calls = $subscriber->calls;

        $client->request('GET', 'https://example.com/llms.txt?x=random');
        self::assertSame(self::EXPECTED, $client->getInternalResponse()->getContent());
        $client->request('GET', 'https://example.com/llms.txt', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseStatusCodeSame(304);
        self::assertSame($calls, $subscriber->calls);
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
