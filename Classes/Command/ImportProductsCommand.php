<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Command;

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
    protected function configure(): void
    {
        $this
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Import target (e.g. girello)')
            ->addOption('phase', null, InputOption::VALUE_OPTIONAL, 'Phase to run: 1, 2 or all', 'all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->success('Hello');

        return Command::SUCCESS;
    }
}