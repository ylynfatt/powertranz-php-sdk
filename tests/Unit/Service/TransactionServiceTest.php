<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Service;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
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

        $result = $this->service->capture(new CaptureRequest('txn-auth-001', Money::of('29.99', 'USD')));

        self::assertInstanceOf(CaptureResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testRefundReturnsApprovedResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('refund_approved'));

        $result = $this->service->refund(new RefundRequest(
            transactionIdentifier: 'txn-sale-001',
            totalAmount:           Money::of('29.99', 'USD'),
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

        $this->service->capture(new CaptureRequest('txn-001', Money::of('50.00', 'USD')));

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

    public function testCaptureBodyContainsTotalAmount(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('capture_approved'));

        $this->service->capture(new CaptureRequest('txn-001', Money::of('75.50', 'USD')));

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(75.5, $body['TotalAmount']);
    }

    public function testRefundBodyContainsCurrencyCode(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('refund_approved'));

        $this->service->refund(new RefundRequest(
            transactionIdentifier: 'txn-001',
            totalAmount:           Money::of('10.00', 'XCD'),
            orderIdentifier:       'order-xcd',
        ));

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('951', $body['CurrencyCode']);  // XCD numeric
    }

    public function testCaptureCurrencyMismatchThrowsValidationException(): void
    {
        $this->expectException(\PowerTranz\Exception\ValidationException::class);

        new CaptureRequest(
            transactionIdentifier: 'txn-001',
            totalAmount:           Money::of('100.00', 'USD'),
            tipAmount:             Money::of('10.00', 'EUR'),  // mismatched currency
        );
    }
}
