<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Streams products from a local CSV file, yielding one chunk per page.
 * Memory-safe for large files: only one chunk is held in memory at a time.
 */
final class CsvFileSource implements ProductSourceInterface
{
    public function __construct(
        private readonly string $csvFile,
        private readonly int $chunkSize = 500,
        private readonly string $delimiter = ',',
    ) {}

    public function fetchPages(): iterable
    {
        $path = GeneralUtility::getFileAbsFileName($this->csvFile);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('CSV file not readable: ' . $path, 1718450200);
        }

        try {
            $headers = fgetcsv($handle, separator: $this->delimiter);

            if ($headers === false) {
                return;
            }

            // strip UTF-8 BOM from first column header if present
            if (str_starts_with($headers[0], "\xEF\xBB\xBF")) {
                $headers[0] = substr($headers[0], 3);
            }

            $chunk = [];

            while (($row = fgetcsv($handle, separator: $this->delimiter)) !== false) {
                if ($row === [null]) {
                    continue;
                }

                if (count($row) !== count($headers)) {
                    throw new \RuntimeException(sprintf(
                        'CSV row has %d columns but header has %d: %s',
                        count($row),
                        count($headers),
                        implode($this->delimiter, $row)
                    ), 1718450201);
                }

                $chunk[] = array_combine($headers, $row);

                if (count($chunk) === $this->chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }
}
