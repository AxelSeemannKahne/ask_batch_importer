<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

/**
 * Summary of a Phase 2 run, for CLI reporting.
 */
final class ProcessingResult
{
    private int $batches = 0;
    private int $records = 0;

    /**
     * @param int $recordCount
     * @return void
     */
    public function addProcessedBatch(int $recordCount): void
    {
        $this->batches++;
        $this->records += $recordCount;
    }

    /**
     * @return int
     */
    public function getBatchCount(): int
    {
        return $this->batches;
    }

    /*
     * * @return int
     */
    public function getRecordCount(): int
    {
        return $this->records;
    }
}