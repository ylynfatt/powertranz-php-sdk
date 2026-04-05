<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Model\Request\CaptureRequest;
use PowerTranz\Model\Request\RefundRequest;
use PowerTranz\Model\Request\VoidRequest;
use PowerTranz\Model\Response\CaptureResponse;
use PowerTranz\Model\Response\RefundResponse;
use PowerTranz\Model\Response\VoidResponse;
use PowerTranz\Service\TransactionService;
use PowerTranz\Tests\Fixture\MockHttpClient;
use PowerTranz\Tests\Fixture\ResponseFixture;

final class TransactionServiceTest extends TestCase
{
    private MockHttpClient $httpClient;
    private TransactionService $service;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $config           = new Configuration('test-id', 'test-pw');
        $this->service    = new TransactionService($this->httpClient, $config);
    }

    public function testCaptureReturnsApprovedResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('capture_approved'));

        $result = $this->service->capture(new CaptureRequest('txn-auth-001', 29.99));

        self::assertInstanceOf(CaptureResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testRefundReturnsApprovedResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('refund_approved'));

        $result = $this->service->refund(new RefundRequest(
            transactionIdentifier: 'txn-sale-001',
            totalAmount:           29.99,
            currencyCode:          CurrencyCode::USD,
            orderIdentifier:       'order-123',
        ));

        self::assertInstanceOf(RefundResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testVoidReturnsApprovedResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('void_approved'));

        $result = $this->service->void(new VoidRequest('txn-auth-001'));

        self::assertInstanceOf(VoidResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testCaptureRequestSentToCorrectEndpoint(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('capture_approved'));

        $this->service->capture(new CaptureRequest('txn-001', 50.00));

        $request = $this->httpClient->getLastRequest();

        self::assertStringContainsString('/capture', $request['url']);
    }

    public function testVoidRequestBodyContainsTransactionIdentifier(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('void_approved'));

        $this->service->void(new VoidRequest('txn-auth-001'));

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('txn-auth-001', $body['TransactionIdentifier']);
    }
}
