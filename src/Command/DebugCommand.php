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

use function count;
use function in_array;

use InvalidArgumentException;

use function is_string;

use Override;
use Silarhi\LlmsTxtBundle\Debug\DumpedFile;
use Silarhi\LlmsTxtBundle\Debug\Inspector;
use Silarhi\LlmsTxtBundle\Debug\Report;
use Silarhi\LlmsTxtBundle\Debug\SectionStats;
use Silarhi\LlmsTxtBundle\Debug\Source;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Where the links come from (route options, link providers, listeners), how many there are per section, how much the
 * files weigh, and what looks wrong: duplicate or relative URLs, another host, a file too large for a context window.
 */
#[AsCommand(name: 'debug:llms-txt', description: 'Shows where the links of llms.txt come from, and checks them')]
final class DebugCommand extends Command
{
    /**
     * Past about 1 MiB, an llms.txt file no longer fits the context window of most models.
     */
    public const DEFAULT_MAX_SIZE = 1024 * 1024;

    private const FORMATS = ['txt', 'json'];

    public function __construct(
        private readonly Inspector $inspector,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('max-size', null, InputOption::VALUE_REQUIRED, 'The size llms.txt should stay under, in bytes', (string) self::DEFAULT_MAX_SIZE)
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fails when a check finds a problem, for a CI')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format: "txt" or "json"', 'txt')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command builds the document, like a request of the file would: it runs
                every listener, and every link provider.

                  <info>php %command.full_name%</info>
                  <info>php %command.full_name% --strict --format=json</info>
                HELP);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf('The format must be "%s", "%s" given.', implode('" or "', self::FORMATS), get_debug_type($format)));
        }

        $maxSize = $input->getOption('max-size');
        if (!is_string($maxSize) || !ctype_digit($maxSize)) {
            throw new InvalidArgumentException('The --max-size option must be a number of bytes.');
        }

        $report = $this->inspector->inspect((int) $maxSize);

        if ('json' === $format) {
            $output->writeln(json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        } else {
            $this->display(new SymfonyStyle($input, $output), $report);
        }

        return true === $input->getOption('strict') && [] !== $report->warnings ? Command::FAILURE : Command::SUCCESS;
    }

    private function display(SymfonyStyle $io, Report $report): void
    {
        $io->title('llms.txt');

        $io->section('Sources, in the order they run');
        $io->table(['Priority', 'Kind', 'Name', 'Section'], array_map(
            static fn (Source $source): array => [$source->priority, $source->kind, $source->name, $source->section ?? '-'],
            $report->sources,
        ));

        $io->section('Sections, in the order of the file');
        $io->table(['Section', 'Links'], array_map(static fn (SectionStats $section): array => [$section->name, $section->links], $report->sections));

        $io->definitionList(
            ['Links' => (string) $report->links],
            ['llms.txt' => null === $report->size ? '-' : Helper::formatMemory($report->size)],
            ['Pages with a content' => (string) $report->pages],
            ['llms-full.txt' => null === $report->fullSize ? 'disabled' : Helper::formatMemory($report->fullSize)],
        );

        $io->section('Dumped files');
        $io->table(['Path', 'Size', 'Modified'], array_map(
            static fn (DumpedFile $file): array => [$file->path, null === $file->size ? 'not dumped' : Helper::formatMemory($file->size), $file->modified ?? '-'],
            $report->files,
        ));

        if ([] === $report->warnings) {
            $io->success('No problem found.');

            return;
        }

        $io->warning(sprintf('%d %s found.', count($report->warnings), 1 === count($report->warnings) ? 'problem' : 'problems'));
        $io->listing($report->warnings);
    }
}
