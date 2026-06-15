<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

use Ask\AskBatchImporter\Config\ProjectConfig;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class DatabaseWriter
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ProjectConfig $config,
    ) {}

    /** @return array{inserted: int, updated: int} */
    public function persist(array $records): array
    {
        $inserted = 0;
        $updated = 0;

        if ($records === []) {
            return ['inserted' => $inserted, 'updated' => $updated];
        }

        $connection = $this->connectionPool->getConnectionByName($this->config->connection);
        $connection->beginTransaction();

        try {
            foreach ($records as $record) {
                $upsertValue = $record[$this->config->upsertKey] ?? null;

                if (
                    $upsertValue !== null
                    && $connection->count('*', $this->config->table, [$this->config->upsertKey => $upsertValue]) > 0
                ) {
                    $connection->update(
                        $this->config->table,
                        $record,
                        [$this->config->upsertKey => $upsertValue]
                    );
                    $updated++;
                } else {
                    $row = $this->config->pid > 0
                        ? array_merge($record, ['pid' => $this->config->pid])
                        : $record;

                    $connection->insert($this->config->table, $row);
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
