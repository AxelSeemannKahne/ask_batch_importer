<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\State;

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

    public function updateLastBatch(string $runId, int $batchNumber): void
    {
        $this->connection()->update(
            self::TABLE,
            ['last_batch' => $batchNumber],
            ['run_id' => $runId]
        );
    }

    public function markFetched(string $runId): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'fetched'],
            ['run_id' => $runId]
        );
    }

    private function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}