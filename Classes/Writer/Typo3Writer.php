<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Typo3Writer implements WriterInterface
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly string $connection,
        private readonly string $table,
        private readonly string $upsertKey,
        private readonly int $pid,
    ) {}

    public static function fromConfig(array $config): static
    {
        return new static(
            connectionPool: GeneralUtility::makeInstance(ConnectionPool::class),
            connection: $config['connection'],
            table: $config['table'],
            upsertKey: $config['upsertKey'],
            pid: (int)$config['pid'],
        );
    }

    /** @return array{inserted: int, updated: int} */
    public function persist(array $records): array
    {
        $inserted = 0;
        $updated = 0;

        if ($records === []) {
            return ['inserted' => $inserted, 'updated' => $updated];
        }

        $connection = $this->connectionPool->getConnectionByName($this->connection);
        $connection->beginTransaction();

        try {
            foreach ($records as $record) {
                $upsertValue = $record[$this->upsertKey] ?? null;

                if (
                    $upsertValue !== null
                    && $connection->count('*', $this->table, [$this->upsertKey => $upsertValue]) > 0
                ) {
                    $connection->update(
                        $this->table,
                        $record,
                        [$this->upsertKey => $upsertValue]
                    );
                    $updated++;
                } else {
                    $row = $this->pid > 0
                        ? array_merge($record, ['pid' => $this->pid])
                        : $record;

                    $connection->insert($this->table, $row);
                    $inserted++;
                }
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return ['inserted' => $inserted, 'updated' => $updated];
    }
}
