<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

interface ProductSourceInterface
{
    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetchItems(?\DateTimeInterface $modifiedSince = null): \Generator;
}