<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

use Ask\AskBatchImporter\Domain\Repository\BatchRepository;
use Ask\AskBatchImporter\Writer\WriterInterface;
use Psr\Log\LoggerInterface;

/**
 * Phase 2: reads staged batches, validates + maps each record,
 * and persists them via the configured writer.
 *
 * Resumable at batch granularity: only batches with status != 'done'
 * are processed. Writers are expected to upsert, so re-processing a
 * partially written batch produces no duplicates.
 */
final class BatchProcessor
{
    public function __construct(
        private readonly BatchRepository $batchRepository,
        private readonly ProductDataMapper $mapper,
        private readonly Validator $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Processes all pending batches for a run.
     *
     * @param WriterInterface $writer the target-specific writer (resolved by the command like TYPO3/products or OXID/oxarticles)
     * @return ProcessingResult summary for CLI output
     */
    public function process(string $runId, WriterInterface $writer): ProcessingResult
    {
        $result = new ProcessingResult();

        foreach ($this->batchRepository->findPending($runId) as $batch) {

            //Set actual batch to processing, so that it won't be picked up by another process or the next run.
            $this->batchRepository->markProcessing($runId, $batch['batch_number']);

            // Validate + map each record
            $records = json_decode($batch['raw_data'], true, 512, JSON_THROW_ON_ERROR);
            $mapped = [];

            // Validation exceptions are expected to be thrown for individual records, and will cause the whole batch to be retried in the next run.
            foreach ($records as $record) {
                $this->validator->validate($record);
                $mapped[] = $this->mapper->map($record);
            }

            // Writer decides how the batch is persisted (transaction, upsert key, ...).
            // Either the whole batch succeeds, or it throws and stays 'processing'
            // for the next run to pick up again.
            $writer->persist($mapped);

            // Mark the batch as done, so that it won't be picked up by another process or the next run.'
            $this->batchRepository->markDone($runId, $batch['batch_number']);

            // Update the result
            $result->addProcessedBatch(count($mapped));

            $this->logger->info('Batch processed', [
                'run'     => $runId,
                'batch'   => $batch['batch_number'],
                'records' => count($mapped),
            ]);
        }

        return $result;
    }
}