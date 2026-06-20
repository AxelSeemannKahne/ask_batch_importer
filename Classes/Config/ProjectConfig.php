<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Config;

final readonly class ProjectConfig
{
    public function __construct(
        public array $fetcher,
        public array $writer,
        public array $mapping,
    ) {}
}
