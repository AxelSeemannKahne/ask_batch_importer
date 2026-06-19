<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Writer;

final class OxidWriter implements WriterInterface
{
    public function persist(array $records): array
    {
        throw new \LogicException('Oxid-Writer is not yet implemented.');
    }
}
