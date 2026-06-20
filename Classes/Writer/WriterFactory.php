<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

use Ask\AskBatchImporter\Config\ProjectConfig;

final class WriterFactory
{
    public function createForConfig(ProjectConfig $config): WriterInterface
    {
        $type = $config->writer['type'];

        return $type::fromConfig($config->writer);
    }
}
