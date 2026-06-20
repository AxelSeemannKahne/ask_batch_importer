<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Config;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ProjectConfigLoader
{
    /**
     * @param string $target
     * @return ProjectConfig
     */
    public function load(string $target): ProjectConfig
    {
        $path = GeneralUtility::getFileAbsFileName(
            'EXT:ask_batch_importer/Configuration/Imports/' . $target . '.yaml'
        );

        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('No import config found for target "%s".', $target));
        }

        $raw = Yaml::parseFile($path);

        return new ProjectConfig(
            fetcher: array_merge(
                ['type' => 'json', 'file' => '', 'chunkSize' => 500, 'delimiter' => ','],
                $raw['fetcher'] ?? [],
            ),
            writer: array_merge(
                ['type' => 'Ask\AskBatchImporter\Writer\Typo3Writer', 'connection' => 'Default', 'table' => '', 'upsertKey' => '', 'pid' => 0],
                $raw['writer'] ?? [],
            ),
            mapping: $raw['mapping'] ?? [],
        );
    }
}
