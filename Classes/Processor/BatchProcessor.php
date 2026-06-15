<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

use Ask\AskBatchImporter\Config\ProjectConfig;
use Ask\AskBatchImporter\Domain\Repository\BatchRepository;
use Ask\AskBatchImporter\Writer\DatabaseWriter;
use Psr\Log\LoggerInterface;

/**
 * Phase 2: validates, maps and persists staged batches.
 * Resumable: batches with status 'processing' (interrupted runs) are retried.
 */
final class BatchProcessor
{
    public function __construct(
        private readonly BatchRepository $batchRepository,
        private readonly ProductDataMapper $mapper,
        private readonly Validator $validator,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(string $runId, DatabaseWriter $writer, ProjectConfig $config): ProcessingResult
    {
        $this->mapper->setMapping($config->mapping);

        $requiredSourceFields = [];
        foreach ($config->mapping as $fieldConfig) {
            if (!empty($fieldConfig['required']) && isset($fieldConfig['source'])) {
                $requiredSourceFields[] = $fieldConfig['source'];
            }
        }

        $result = new ProcessingResult();

        foreach ($this->batchRepository->findPending($runId) as $batch) {
            $this->batchRepository->markProcessing($runId, $batch['batch_number']);

            $records = json_decode($batch['raw_data'], true, 512, JSON_THROW_ON_ERROR);
            $mapped = [];

            foreach ($records as $record) {
                $this->validator->validate($record, $requiredSourceFields);
                $mapped[] = $this->mapper->map($record);
            }

            $writer->persist($mapped);

            $this->batchRepository->markDone($runId, $batch['batch_number']);
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
