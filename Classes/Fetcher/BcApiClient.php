<?php


declare(strict_types=1);

namespace Ask\AskBatchImporter\Fetcher;

use Ask\AskBatchImporter\Fetcher\Dto\BcConnectionConfig;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Thin client for Microsoft Business Central.
 *
 * Handles OAuth2 (client credentials) against Azure AD and reads items
 * from the BC OData API, following server-driven paging (@odata.nextLink).
 */
final class BcApiClient implements ProductSourceInterface
{
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly BcConnectionConfig $config,
    ) {
    }

    /**
     * Yields every item from BC, one record at a time.
     *
     * Paging is handled internally: the client follows @odata.nextLink until
     * BC stops returning one. The caller (BatchFetcher) is responsible for
     * chunking the stream into 500-record staging batches.
     *
     * @param \DateTimeInterface|null $modifiedSince optional delta filter
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetchItems(?\DateTimeInterface $modifiedSince = null): \Generator
    {
        $url = $this->buildItemsUrl($modifiedSince);

        while ($url !== null) {
            $page = $this->getJson($url);

            foreach ($page['value'] ?? [] as $record) {
                yield $record;
            }

            // server-driven paging: BC tells us where the next page lives
            $url = $page['@odata.nextLink'] ?? null;
        }
    }

    public function fetchPages(): \Generator
    {
        yield iterator_to_array($this->fetchItems());
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
            'client_id'     => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
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
            $this->config->tenantId
        );
    }

    private function buildItemsUrl(?\DateTimeInterface $modifiedSince): string
    {
        $url = sprintf(
            'https://api.businesscentral.dynamics.com/v2.0/%s/%s/api/v2.0/companies(%s)/items',
            $this->config->tenantId,
            $this->config->environment,
            $this->config->companyId
        );

        if ($modifiedSince !== null) {
            $url .= '?$filter=lastModifiedDateTime gt '
                . $modifiedSince->format('Y-m-d\TH:i:s\Z');
        }

        return $url;
    }
}