<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class BatchRepository
{
    private const TABLE = 'tx_askbatchimporter_batch';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * Stores one fetched batch as raw JSON (Phase 1).
     *
     * @param array<int, array<string, mixed>> $records
     */
    public function store(string $runId, int $batchNumber, array $records): void
    {
        $this->connection()->insert(self::TABLE, [
            'run_id' => $runId,
            'batch_number' => $batchNumber,
            'raw_data' => json_encode($records, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'created' => time(),
        ]);
    }

    private function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}