<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Fixture;

use PowerTranz\Http\HttpClientInterface;

/**
 * In-memory HTTP client double for unit tests.
 *
 * Queue responses with {@see addResponse()} and they will be returned
 * in FIFO order. After all queued responses are consumed, an exception is thrown.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<array{status: int, body: string, headers: array<string, string>}|\Throwable> */
    private array $queue = [];

    /** @var list<array{url: string, headers: array<string, string>, body: string}> */
    private array $requests = [];

    /**
     * @param array<string, string> $headers
     */
    public function addResponse(int $status, string $body, array $headers = []): void
    {
        $this->queue[] = ['status' => $status, 'body' => $body, 'headers' => $headers];
    }

    public function addJsonResponse(int $status, mixed $data): void
    {
        $this->addResponse($status, json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function addException(\Throwable $exception): void
    {
        $this->queue[] = $exception;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function post(string $url, array $headers, string $body): array
    {
        if ($this->queue === []) {
            throw new \UnderflowException('MockHttpClient: no more queued responses.');
        }

        $this->requests[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * Returns all requests that were sent to this client.
     *
     * @return list<array{url: string, headers: array<string, string>, body: string}>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getLastRequest(): ?array
    {
        return $this->requests === [] ? null : end($this->requests);
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }
}
