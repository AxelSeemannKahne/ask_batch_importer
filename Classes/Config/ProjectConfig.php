<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Config;

final readonly class ProjectConfig
{
    public function __construct(
        public array $fetcher,
        public string $writer,
        public string $connection,
        public string $table,
        public string $upsertKey,
        public int $pid,
        public array $mapping,
    ) {}
}
