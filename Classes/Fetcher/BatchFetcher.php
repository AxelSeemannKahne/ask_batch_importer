<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use Ask\AskBatchImporter\Domain\Repository\BatchRepository;
use Ask\AskBatchImporter\State\ImportRun;
use Ask\AskBatchImporter\Domain\Repository\ImportStateRepository;

/**
 * Phase 1: pulls pages from the source and stores each page
 * as one staging batch. No further chunking.
 */
final class BatchFetcher
{
    public function __construct(
        private readonly ProductSourceInterface $source,
        private readonly BatchRepository $batchRepository,
        private readonly ImportStateRepository $stateRepository,
    ) {}

    public function fetch(string $target): ImportRun
    {
        $run = $this->stateRepository->createRun($target);

        $batchNumber = 0;

        foreach ($this->source->fetchPages() as $page) {
            $batchNumber++;
            $this->batchRepository->store($run->runId, $batchNumber, $page);
            $this->stateRepository->updateLastBatch($run->runId, $batchNumber);
        }

        $this->stateRepository->markFetched($run->runId);

        return $run;
    }
}