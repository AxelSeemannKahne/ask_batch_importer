<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads products from a local JSON file for Testing
 * Drop-in replacement for development / testing without API access.
 */
final class JsonFileSource implements ProductSourceInterface
{
    public function __construct(
        private readonly string $fixtureFile =
        'EXT:ask_batch_importer/Resources/Private/Fixtures/bc_items.json',
    ) {
    }

    public function fetchItems(?\DateTimeInterface $modifiedSince = null): \Generator
    {
        $path = GeneralUtility::getFileAbsFileName($this->fixtureFile);
        $json = file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException('Fixture not readable: ' . $path, 1718450101);
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        // same shape BC delivers: records live under "value"
        foreach ($data['value'] ?? [] as $record) {
            yield $record;
        }
    }
}