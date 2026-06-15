<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Command;

use Ask\AskBatchImporter\Domain\Repository\BatchRepository;
use Ask\AskBatchImporter\State\ImportStateRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ask:import:flush',
    description: 'Deletes all staged batches and run records (clean slate for testing).',
)]
final class FlushStagingCommand extends Command
{
    public function __construct(
        private readonly BatchRepository $batchRepository,
        private readonly ImportStateRepository $stateRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->batchRepository->truncateAll();
        $this->stateRepository->truncateAll();

        $io->success('Staging cleared.');

        return Command::SUCCESS;
    }
}