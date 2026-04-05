<?php

declare(strict_types=1);

namespace PowerTranz\Http;

use PowerTranz\Config\Configuration;
use PowerTranz\Exception\NetworkException;

/**
 * Decorator that adds exponential-backoff retry logic around any {@see HttpClientInterface}.
 *
 * Retries on:
 *   - {@see NetworkException} (transport failures)
 *   - HTTP 429 (rate limited)
 *   - HTTP 500, 502, 503, 504 (transient server errors)
 *
 * The delay doubles on each attempt starting from {@see Configuration::$retryBaseDelay}
 * with a small random jitter to avoid thundering-herd scenarios.
 */
final class RetryMiddleware implements HttpClientInterface
{
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly Configuration $config,
    ) {
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function post(string $url, array $headers, string $body): array
    {
        $attempt   = 0;
        $lastError = null;
        /** @var array{status: int, body: string, headers: array<string, string>}|null $lastResponse */
        $lastResponse = null;

        do {
            if ($attempt > 0) {
                $delay = $this->calculateDelay($attempt);
                $this->config->logger->debug(
                    sprintf('PowerTranz: retrying request (attempt %d/%d) after %.2fs', $attempt + 1, $this->config->maxRetries + 1, $delay)
                );
                usleep((int) ($delay * 1_000_000));
            }

            try {
                $response = $this->inner->post($url, $headers, $body);

                if (!$this->isRetryable($response['status'])) {
                    return $response;
                }

                $lastResponse = $response;
                $lastError    = null;
                $attempt++;
            } catch (NetworkException $e) {
                $lastError = $e;
                $attempt++;
            }
        } while ($attempt <= $this->config->maxRetries);

        if ($lastError !== null) {
            throw $lastError;
        }

        // Last response was a retryable HTTP status — return it to let the service layer handle it
        /** @var array{status: int, body: string, headers: array<string, string>} $lastResponse */
        return $lastResponse;
    }

    private function isRetryable(int $status): bool
    {
        return in_array($status, self::RETRYABLE_STATUS_CODES, true);
    }

    private function calculateDelay(int $attempt): float
    {
        $base  = $this->config->retryBaseDelay * (2 ** ($attempt - 1));
        $jitter = (mt_rand(0, 100) / 1000); // up to 0.1s jitter

        return $base + $jitter;
    }
}
