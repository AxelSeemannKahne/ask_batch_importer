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

    public function createForConfig(ProjectConfig $config): WriterInterface
    {
        return match ($config->writer) {
            'oxid' => new OxidWriter(),
            default => new Typo3Writer($this->connectionPool, $config),
        };
    }
}
