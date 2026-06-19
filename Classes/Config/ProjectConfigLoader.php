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
            writer: $raw['writer'] ?? 'typo3',
            connection: $raw['connection'] ?? 'Default',
            table: $raw['table'] ?? '',
            upsertKey: $raw['upsertKey'] ?? '',
            pid: (int)($raw['pid'] ?? 0),
            mapping: $raw['mapping'] ?? [],
        );
    }
}
