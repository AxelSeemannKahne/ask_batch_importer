<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

use Ask\AskBatchImporter\Config\ProjectConfig;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class WriterFactory
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param ProjectConfig $config
     * @return DatabaseWriter
     */
    public function createForConfig(ProjectConfig $config): DatabaseWriter
    {
        return new DatabaseWriter($this->connectionPool, $config);
    }
}
