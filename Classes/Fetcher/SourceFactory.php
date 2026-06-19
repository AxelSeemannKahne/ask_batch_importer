<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use Ask\AskBatchImporter\Config\ProjectConfig;

final class SourceFactory
{
    public function createForConfig(ProjectConfig $config): ProductSourceInterface
    {
        $fetcher = $config->fetcher;
        $type = $fetcher['type'];

        return $type::fromConfig($fetcher);
    }
}
