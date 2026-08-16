<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use JsonSerializable;
use PowerTranz\Config\Configuration;
use PowerTranz\Exception\ApiException;
use PowerTranz\Exception\AuthenticationException;
use PowerTranz\Http\HttpClientInterface;

/**
 * Base class for all SDK services.
 *
 * Handles:
 *   - Injecting PowerTranz authentication headers
 *   - JSON serialisation of request bodies
 *   - PSR-3 debug logging (with automatic card data redaction)
 *   - HTTP error mapping to typed exceptions
 */
abstract class AbstractService
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly Configuration $config,
    ) {
    }

    /**
     * Send a POST request to the given endpoint path and return the decoded response array.
     *
     * @param  string        $endpoint Relative path, e.g. 'spi/sale' or 'capture'
     * @param  JsonSerializable $body
     * @return array<string, mixed>
     *
     * @throws AuthenticationException On 401/403.
     * @throws ApiException            On other 4xx/5xx responses.
     */
    protected function post(string $endpoint, JsonSerializable $body): array
    {
        $url     = $this->config->baseUrl() . ltrim($endpoint, '/');
        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        $this->config->logger->debug('PowerTranz request', [
            'endpoint' => $endpoint,
            'body'     => $this->redactBody(json_decode($payload, true, 512, JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->httpClient->post(
            url:     $url,
            headers: $this->buildAuthHeaders(),
            body:    $payload,
        );

        $this->config->logger->debug('PowerTranz response', [
            'status'   => $response['status'],
            'endpoint' => $endpoint,
        ]);

        return $this->handleResponse($response['status'], $response['body']);
    }

    /**
     * @return array<string, string>
     */
    private function buildAuthHeaders(): array
    {
        return [
            'Content-Type'                  => 'application/json',
            'Accept'                        => 'application/json',
            'PowerTranz-PowerTranzId'       => $this->config->powerTranzId,
            'PowerTranz-PowerTranzPassword' => $this->config->powerTranzPassword,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResponse(int $status, string $body): array
    {
        if ($status === 401 || $status === 403) {
            throw new AuthenticationException(
                'PowerTranz authentication failed. Check your PowerTranz-PowerTranzId and PowerTranz-PowerTranzPassword.',
                $status,
                $body,
            );
        }

        if ($status >= 400) {
            throw ApiException::fromResponse($status, $body);
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Mask a decoded request body for safe logging.
     *
     * Most endpoints send an object, but /spi/payment sends the bare SpiToken as
     * a JSON string. That token authorises a financial transaction for the next
     * five minutes, so it is masked entirely rather than written to the log.
     */
    private function redactBody(mixed $decoded): mixed
    {
        if (is_array($decoded)) {
            return $this->redactPayload($decoded);
        }

        return is_string($decoded) ? '***' : $decoded;
    }

    /**
     * Returns a copy of the payload with sensitive card data masked for safe logging.
     *
     * - CardPan: first 6 + last 4 digits visible (BIN + last4), middle masked
     * - CardCvv: replaced with ***
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        if (isset($payload['Source']['CardPan'])) {
            $pan = (string) $payload['Source']['CardPan'];
            $len = strlen($pan);
            $payload['Source']['CardPan'] = $len > 10
                ? substr($pan, 0, 6) . str_repeat('*', $len - 10) . substr($pan, -4)
                : str_repeat('*', $len);
        }

        if (isset($payload['Source']['CardCvv'])) {
            $payload['Source']['CardCvv'] = '***';
        }

        return $payload;
    }
}
