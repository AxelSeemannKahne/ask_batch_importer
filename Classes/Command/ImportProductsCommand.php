<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Command;

use Ask\AskBatchImporter\Fetcher\BatchFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ask:import:products',
    description: 'Imports product data from Microsoft Business Central.',
)]
final class ImportProductsCommand extends Command
{
    public function __construct(
        private readonly BatchFetcher $batchFetcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('target', null, InputOption::VALUE_REQUIRED, 'Import target (e.g. exampleproject)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $target = (string)$input->getOption('target');

        $io->writeln(sprintf('Fetching for target "%s"...', $target));

        $run = $this->batchFetcher->fetch($target);

        $io->success(sprintf('Done. run_id=%s, batches=%d', $run->runId, $run->lastBatch));

        return Command::SUCCESS;
    }
}