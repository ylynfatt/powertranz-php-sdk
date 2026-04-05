<?php

declare(strict_types=1);

namespace PowerTranz\Http;

use PowerTranz\Exception\NetworkException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adapts any PSR-18 HTTP client to the SDK's {@see HttpClientInterface}.
 *
 * Provide your preferred PSR-18 client (Guzzle, Symfony HTTP Client, etc.) and
 * matching PSR-17 request/stream factories.
 *
 * Example with Guzzle + nyholm/psr7:
 *   $psr7Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
 *   $httpClient  = new \GuzzleHttp\Client();
 *   $adapter     = new PsrHttpClient($httpClient, $psr7Factory, $psr7Factory);
 */
final class PsrHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function post(string $url, array $headers, string $body): array
    {
        $stream  = $this->streamFactory->createStream($body);
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withBody($stream);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), 0, $e);
        }

        $responseHeaders = [];

        foreach ($response->getHeaders() as $name => $values) {
            $responseHeaders[strtolower($name)] = implode(', ', $values);
        }

        return [
            'status'  => $response->getStatusCode(),
            'body'    => (string) $response->getBody(),
            'headers' => $responseHeaders,
        ];
    }
}
