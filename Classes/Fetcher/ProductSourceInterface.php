<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

interface ProductSourceInterface
{
    public function fetchPages(): iterable;
}