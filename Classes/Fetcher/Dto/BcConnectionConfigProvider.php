<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher\Dto;

/**
 * Immutable connection settings for a Business Central tenant.
 */
final readonly class BcConnectionConfig
{
    public function __construct(
        public string $tenantId,
        public string $environment,
        public string $clientId,
        public string $clientSecret,
        public string $companyId,
    ) {}
}