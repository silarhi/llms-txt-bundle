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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\LlmsTxtBundle\Command\DumpCommand;
use Silarhi\LlmsTxtBundle\Service\DumperInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class DumpCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function providePorts(): iterable
    {
        yield 'HTTPS' => ['https://example.com:8443/app/', 'https', 8443, 80];
        yield 'HTTP' => ['http://example.com:8080', 'http', 443, 8080];
    }

    #[DataProvider('providePorts')]
    public function testAppliesThePortOfTheBaseUrl(string $baseUrl, string $scheme, int $httpsPort, int $httpPort): void
    {
        $context = new RequestContext();

        $this->createTester($context)->execute(['--base-url' => $baseUrl]);

        self::assertSame($scheme, $context->getScheme());
        self::assertSame('example.com', $context->getHost());
        self::assertSame($httpsPort, $context->getHttpsPort());
        self::assertSame($httpPort, $context->getHttpPort());
    }

    public function testRejectsARelativeBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URL must be absolute, e.g. "https://example.com", "/app" given.');

        $this->createTester(new RequestContext())->execute(['--base-url' => '/app']);
    }

    private function createTester(RequestContext $context): CommandTester
    {
        $dumper = self::createStub(DumperInterface::class);
        $dumper->method('dump')->willReturn('/var/www/public/llms.txt');

        return new CommandTester(new DumpCommand($dumper, new UrlGenerator(new RouteCollection(), $context)));
    }
}
