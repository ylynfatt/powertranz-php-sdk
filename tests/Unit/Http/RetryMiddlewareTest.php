<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Exception\NetworkException;
use PowerTranz\Http\RetryMiddleware;
use PowerTranz\Tests\Fixture\MockHttpClient;

final class RetryMiddlewareTest extends TestCase
{
    public function testPassesThroughSuccessfulResponse(): void
    {
        $inner  = new MockHttpClient();
        $config = new Configuration('id', 'pw', maxRetries: 3);
        $inner->addResponse(200, '{"ok":true}');

        $middleware = new RetryMiddleware($inner, $config);
        $result     = $middleware->post('https://example.com', [], '{}');

        self::assertSame(200, $result['status']);
        self::assertSame(1, $inner->getRequestCount());
    }

    public function testRetriesOn503AndEventuallySucceeds(): void
    {
        $inner  = new MockHttpClient();
        $config = new Configuration('id', 'pw', maxRetries: 2, retryBaseDelay: 0.0);
        $inner->addResponse(503, 'Service Unavailable');
        $inner->addResponse(503, 'Service Unavailable');
        $inner->addResponse(200, '{"ok":true}');

        $middleware = new RetryMiddleware($inner, $config);
        $result     = $middleware->post('https://example.com', [], '{}');

        self::assertSame(200, $result['status']);
        self::assertSame(3, $inner->getRequestCount());
    }

    public function testDoesNotRetryOn400(): void
    {
        $inner  = new MockHttpClient();
        $config = new Configuration('id', 'pw', maxRetries: 3, retryBaseDelay: 0.0);
        $inner->addResponse(400, 'Bad Request');

        $middleware = new RetryMiddleware($inner, $config);
        $result     = $middleware->post('https://example.com', [], '{}');

        self::assertSame(400, $result['status']);
        self::assertSame(1, $inner->getRequestCount());
    }

    public function testRetriesOnNetworkExceptionAndRethrowsAfterExhaustion(): void
    {
        $inner  = new MockHttpClient();
        $config = new Configuration('id', 'pw', maxRetries: 2, retryBaseDelay: 0.0);

        // Queue more exceptions than retries to ensure exhaustion
        $inner->addException(new NetworkException('Connection refused'));
        $inner->addException(new NetworkException('Connection refused'));
        $inner->addException(new NetworkException('Connection refused'));

        $middleware = new RetryMiddleware($inner, $config);

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection refused');

        $middleware->post('https://example.com', [], '{}');
    }
}
