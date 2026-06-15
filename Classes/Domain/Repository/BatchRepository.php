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
     * @param string $runId
     * @param int $batchNumber
     * @param array $records
     * @return void
     * @throws \JsonException
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

    /**
     * @param string $runId
     * @return iterable
     * @throws \Doctrine\DBAL\Exception
     */
    public function findPending(string $runId): iterable
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $qb
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $qb->expr()->eq('run_id', $qb->createNamedParameter($runId)),
                $qb->expr()->or(
                    $qb->expr()->eq('status', $qb->createNamedParameter('pending')),
                    $qb->expr()->eq('status', $qb->createNamedParameter('processing')),
                ),
            )
            ->orderBy('batch_number', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string $runId
     * @param int $batchNumber
     * @return void
     */
    public function markProcessing(string $runId, int $batchNumber): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'processing'],
            ['run_id' => $runId, 'batch_number' => $batchNumber],
        );
    }

    /*
     *  @param  string $runId
     */
    public function markDone(string $runId, int $batchNumber): void
    {
        $this->connection()->update(
            self::TABLE,
            ['status' => 'done'],
            ['run_id' => $runId, 'batch_number' => $batchNumber],
        );
    }

    /**
     * @return Connection
     */
    private function connection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }
}