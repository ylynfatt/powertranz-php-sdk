<?php

declare(strict_types=1);

namespace PowerTranz\Http;

use PowerTranz\Config\Configuration;
use PowerTranz\Exception\NetworkException;

/**
 * Zero-dependency cURL-based HTTP client.
 *
 * Used when no PSR-18 client is provided to {@see \PowerTranz\PowerTranzClient}.
 * Requires the PHP `curl` extension.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly Configuration $config)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException(
                'The PHP curl extension is required when no PSR-18 HTTP client is provided.'
            );
        }
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function post(string $url, array $headers, string $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
        ]);

        $raw      = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            throw new NetworkException(
                sprintf('cURL error (%d): %s', $errno, $error)
            );
        }

        $rawHeaders  = substr((string) $raw, 0, $headerSize);
        $responseBody = substr((string) $raw, $headerSize);

        return [
            'status'  => $httpCode,
            'body'    => $responseBody,
            'headers' => $this->parseHeaders($rawHeaders),
        ];
    }

    /**
     * @param  array<string,string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $parsed = [];

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))] = trim($value);
        }

        return $parsed;
    }
}
