<?php


declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use Ask\AskBatchImporter\Fetcher\Dto\BcConnectionConfigProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Fetches product data from Microsoft Business Central via OData API.
 *
 * Handles OAuth2 (client credentials) against Azure AD and follows
 * server-driven paging (@odata.nextLink). Each API page becomes one staging batch.
 */
final class BcApiSource implements ProductSourceInterface
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;
    private ?\Ask\AskBatchImporter\Fetcher\Dto\BcConnectionConfig $config = null;

    private function config(): \Ask\AskBatchImporter\Fetcher\Dto\BcConnectionConfig
    {
        return $this->config ??= $this->configProvider->create();
    }

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly BcConnectionConfigProvider $configProvider,
    ) {
    }

    public function fetchPages(): iterable
    {
        $url = $this->buildItemsUrl();

        while ($url !== null) {
            $page = $this->getJson($url);
            yield $page['value'] ?? [];
            $url = $page['@odata.nextLink'] ?? null;
        }
    }

    /**
     * Performs an authenticated GET and returns the decoded JSON body.
     *
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $this->getAccessToken())
            ->withHeader('Accept', 'application/json');

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('BC API request failed (%d): %s', $response->getStatusCode(), $url),
                1718450001
            );
        }

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Returns a valid access token, fetching a new one only when expired.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->config()->clientId,
            'client_secret' => $this->config()->clientSecret,
            'scope'         => 'https://api.businesscentral.dynamics.com/.default',
        ]);

        $request = $this->requestFactory
            ->createRequest('POST', $this->buildTokenUrl())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream($body));

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('BC OAuth token request failed (%d)', $response->getStatusCode()),
                1718450002
            );
        }

        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->accessToken = $data['access_token'];
        // refresh 60s early to avoid edge-of-expiry failures mid-run
        $this->tokenExpiresAt = time() + (int)$data['expires_in'] - 60;

        return $this->accessToken;
    }

    private function buildTokenUrl(): string
    {
        return sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            $this->config()->tenantId
        );
    }

    private function buildItemsUrl(): string
    {
        return sprintf(
            'https://api.businesscentral.dynamics.com/v2.0/%s/%s/api/v2.0/companies(%s)/items',
            $this->config()->tenantId,
            $this->config()->environment,
            $this->config()->companyId
        );
    }
}