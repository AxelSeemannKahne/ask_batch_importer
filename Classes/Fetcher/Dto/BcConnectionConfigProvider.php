<?php

declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher\Dto;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class BcConnectionConfigProvider
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException
     * @throws \TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException
     */
    public function create(): BcConnectionConfig
    {
        $config = $this->extensionConfiguration->get('ask_batch_importer');

        return new BcConnectionConfig(
            tenantId: (string)($config['bc']['tenantId'] ?? ''),
            environment: (string)($config['bc']['environment'] ?? 'production'),
            clientId: (string)($config['bc']['clientId'] ?? ''),

            // secrets are not available in the extension configuration, so we read them from environment variables
            clientSecret: (string)(getenv('BC_CLIENT_SECRET') ?: ''),
            companyId: (string)($config['bc']['companyId'] ?? ''),
        );
    }
}
