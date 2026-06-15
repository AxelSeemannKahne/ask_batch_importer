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

    /**
     * @param array $records
     * @return void
     * @throws \Doctrine\DBAL\Exception
     * @throws \Throwable
     */
    public function persist(array $records): void
    {
        if ($records === []) {
            return;
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
                } else {
                    $row = $this->config->pid > 0
                        ? array_merge($record, ['pid' => $this->config->pid])
                        : $record;

                    $connection->insert($this->config->table, $row);
                }
            }

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
