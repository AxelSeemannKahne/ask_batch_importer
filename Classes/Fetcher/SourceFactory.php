<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use Ask\AskBatchImporter\Config\ProjectConfig;

final class SourceFactory
{
    public function __construct(
        private readonly BcApiSource $bcFetcher,
    ) {}

    public function createForConfig(ProjectConfig $config): ProductSourceInterface
    {
        $fetcher = $config->fetcher;

        return match ($fetcher['type']) {
            'bc'  => $this->bcFetcher,
            'csv' => new CsvFileSource(
                csvFile: $fetcher['file'],
                chunkSize: (int)$fetcher['chunkSize'],
                delimiter: $fetcher['delimiter'],
            ),
            default => new JsonFileSource(
                fixtureFile: $fetcher['file'],
                chunkSize: (int)$fetcher['chunkSize'],
            ),
        };
    }
}
