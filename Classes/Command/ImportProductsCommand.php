<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Command;

use Ask\AskBatchImporter\Config\ProjectConfigLoader;
use Ask\AskBatchImporter\Fetcher\BatchFetcher;
use Ask\AskBatchImporter\Processor\BatchProcessor;
use Ask\AskBatchImporter\Domain\Repository\ImportStateRepository;
use Ask\AskBatchImporter\Writer\WriterFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ask:import:products',
    description: 'Imports product data from a pluggable source (e.g. Business Central) into a pluggable target.',
)]
final class ImportProductsCommand extends Command
{
    public function __construct(
        private readonly ProjectConfigLoader $configLoader,
        private readonly WriterFactory $writerFactory,
        private readonly BatchFetcher $batchFetcher,
        private readonly BatchProcessor $batchProcessor,
        private readonly ImportStateRepository $stateRepository,
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption('target', null, InputOption::VALUE_REQUIRED, 'Import target (e.g. exampleproject)');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $target = (string)$input->getOption('target');

        $config = $this->configLoader->load($target);
        $writer = $this->writerFactory->createForConfig($config);

        $io->writeln(sprintf('Phase 1: fetching "%s" via %s...', $target, $config->fetcher['type']));
        $run = $this->batchFetcher->fetch($target, $config);

        $io->writeln(
            sprintf('Phase 2: processing run %s → %s → %s...', $run->runId, $config->writer['type'], $config->writer['table'])
        );

        try {
            $result = $this->batchProcessor->process($run->runId, $writer, $config);
            $this->stateRepository->markProcessed($run->runId);
        } catch (\Throwable $e) {
            $this->stateRepository->markFailed($run->runId);
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(
            sprintf(
                'Done. run_id=%s  batches=%d  inserted=%d  updated=%d',
                $run->runId,
                $result->getBatchCount(),
                $result->getInsertedCount(),
                $result->getUpdatedCount(),
            )
        );

        return Command::SUCCESS;
    }
}