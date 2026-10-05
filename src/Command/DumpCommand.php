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

namespace Silarhi\LlmsTxtBundle\Command;

use InvalidArgumentException;

use function is_string;

use Override;
use Silarhi\LlmsTxtBundle\Service\DumperInterface;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RequestContextAwareInterface;

#[AsCommand(name: 'llms-txt:dump', description: 'Dumps the llms.txt file')]
final class DumpCommand extends Command
{
    public function __construct(
        private readonly DumperInterface $dumper,
        private readonly RequestContextAwareInterface $router,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'The directory to dump the file into, "llms_txt.dump_directory" by default')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'The base URL of the absolute URLs, e.g. "https://example.com", "framework.router.default_uri" by default');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = $input->getOption('base-url');
        if (is_string($baseUrl) && '' !== $baseUrl) {
            $this->applyBaseUrl($baseUrl);
        }

        $target = $input->getArgument('target');
        $path = $this->dumper->dump(is_string($target) ? $target : null);

        (new SymfonyStyle($input, $output))->success(sprintf('Dumped "%s".', $path));

        return Command::SUCCESS;
    }

    /**
     * There is no request in a command: the URLs are generated from the request context of the router.
     */
    private function applyBaseUrl(string $baseUrl): void
    {
        $parts = parse_url($baseUrl);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException(sprintf('The base URL must be absolute, e.g. "https://example.com", "%s" given.', $baseUrl));
        }

        $context = $this->router->getContext();
        $context->setScheme($parts['scheme']);
        $context->setHost($parts['host']);
        $context->setBaseUrl(rtrim($parts['path'] ?? '', '/'));

        if (isset($parts['port'])) {
            'https' === $parts['scheme'] ? $context->setHttpsPort($parts['port']) : $context->setHttpPort($parts['port']);
        }
    }
}
