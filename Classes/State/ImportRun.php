<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\State;

final readonly class ImportRun
{
    public function __construct(
        public string $runId,
        public string $target,
        public string $phase,
        public int $lastBatch,
        public string $status,
        public int $created,
    ) {}

    /**
     * @param string $target
     * @return self
     * @throws \Random\RandomException
     */
    public static function createNew(string $target): self
    {
        return new self(
            runId: bin2hex(random_bytes(16)),
            target: $target,
            phase: 'fetching',
            lastBatch: 0,
            status: 'fetching',
            created: time(),
        );
    }

    /**
     * @param array $row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            runId: (string)$row['run_id'],
            target: (string)$row['target'],
            phase: (string)$row['phase'],
            lastBatch: (int)$row['last_batch'],
            status: (string)$row['status'],
            created: (int)$row['created'],
        );
    }
}