<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

interface WriterInterface
{
    public static function fromConfig(array $config): static;

    /** @return array{inserted: int, updated: int} */
    public function persist(array $records): array;
}
