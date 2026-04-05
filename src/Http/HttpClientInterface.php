<?php

declare(strict_types=1);

namespace PowerTranz\Http;

use PowerTranz\Exception\NetworkException;

/**
 * Thin HTTP contract used internally by the SDK.
 *
 * Returns a simple array shape rather than a PSR-7 response object so that the
 * service layer remains decoupled from any specific PSR-7 implementation.
 */
interface HttpClientInterface
{
    /**
     * Send a POST request with a JSON body.
     *
     * @param  string               $url
     * @param  array<string,string> $headers
     * @param  string               $body    JSON-encoded request body
     * @return array{status: int, body: string, headers: array<string, string>}
     *
     * @throws NetworkException On transport-level failures.
     */
    public function post(string $url, array $headers, string $body): array;
}
