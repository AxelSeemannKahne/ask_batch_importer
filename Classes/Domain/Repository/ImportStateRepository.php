<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Domain\Repository;

use Ask\AskBatchImporter\State\ImportRun;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reads and writes the state of an import run (tx_askbatchimporter_run).
 *
 * Resume is not implemented: every Phase 1 run creates a new run.
 * Old runs remain in the table as history.
 */
final class ImportStateRepository
{
    private const TABLE = 'tx_askbatchimporter_run';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param string $target
     * @return ImportRun
     * @throws \Random\RandomException
     */
    public function createRun(string $target): ImportRun
    {
        $run = ImportRun::createNew($target);

        $this->connection()->insert(self::TABLE, [
            'run_id' => $run->runId,
            'target' => $run->target,
            'phase' => $run->phase,
            'last_batch' => $run->lastBatch,
            'status' => $run->status,
            'created' => $run->created,
        ]);

        return $run;
    }

    /**
     * @param string $runId
     * @param int $batchNumber
     * @return void
     */
    public function updateLastBatch(string $runId, int $batchNumber): void
    {
        $this->connection()->update(
            self::TABLE,
            ['last_batch' => $batchNumber],
            ['run_id' => $runId]
        );
    }

    /**
     * @param string $runId
     * @return void
     */
    public function markFetched(string $runId): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'fetched'],
            ['run_id' => $runId]
        );
    }

    /**
     * @param string $runId
     * @return void
     */
    public function markProcessed(string $runId): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'processed'],
            ['run_id' => $runId]
        );
    }

    /**
     * @param string $runId
     * @return void
     */
    public function markFailed(string $runId): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'failed'],
            ['run_id' => $runId]
        );
    }

    public function truncateAll(): void
    {
        $this->connection()->truncate(self::TABLE);
    }

    /**
     * @return Connection
     */
    private function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}
