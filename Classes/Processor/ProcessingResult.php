<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Processor;

final class ProcessingResult
{
    private int $batches = 0;
    private int $inserted = 0;
    private int $updated = 0;

    public function addProcessedBatch(int $inserted, int $updated): void
    {
        $this->batches++;
        $this->inserted += $inserted;
        $this->updated += $updated;
    }

    public function getBatchCount(): int
    {
        return $this->batches;
    }

    public function getInsertedCount(): int
    {
        return $this->inserted;
    }

    public function getUpdatedCount(): int
    {
        return $this->updated;
    }
}
