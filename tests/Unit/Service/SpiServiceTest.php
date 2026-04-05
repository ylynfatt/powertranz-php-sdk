<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Exception\AuthenticationException;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\Parts\BrowserDetails;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\PaymentRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\AuthResponse;
use PowerTranz\Model\Response\PaymentResponse;
use PowerTranz\Model\Response\SaleResponse;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\Service\SpiService;
use PowerTranz\Tests\Fixture\MockHttpClient;
use PowerTranz\Tests\Fixture\ResponseFixture;

final class SpiServiceTest extends TestCase
{
    private MockHttpClient $httpClient;
    private SpiService $service;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $config           = new Configuration('test-id', 'test-pw');
        $this->service    = new SpiService($this->httpClient, $config);
    }

    public function testSaleReturnsApprovedSaleResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $result = $this->service->sale($this->makeSaleRequest());

        self::assertInstanceOf(SaleResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testSaleReturnsThreeDsChallengeOnRedirectCode(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $result = $this->service->sale($this->makeSaleRequest());

        self::assertInstanceOf(ThreeDSecureChallenge::class, $result);
        self::assertSame('spi-token-abc123xyz', $result->spiToken);
        self::assertTrue($result->isIframe());
    }

    public function testAuthorizeReturnsAuthResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('auth_approved'));

        $request = new AuthRequest(
            totalAmount:           29.99,
            currencyCode:          CurrencyCode::USD,
            orderIdentifier:       'order-123',
            transactionIdentifier: 'txn-001',
            source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );

        $result = $this->service->authorize($request);

        self::assertInstanceOf(AuthResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testPaymentReturnsPaymentResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('payment_approved'));

        $result = $this->service->payment(new PaymentRequest('spi-token-abc123xyz'));

        self::assertInstanceOf(PaymentResponse::class, $result);
        self::assertTrue($result->approved);
    }

    public function testThrowsAuthenticationExceptionOn401(): void
    {
        $this->httpClient->addResponse(401, '{"ResponseMessage":"Unauthorized"}');

        $this->expectException(AuthenticationException::class);

        $this->service->sale($this->makeSaleRequest());
    }

    public function testAuthHeadersAreSentWithEveryRequest(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $this->service->sale($this->makeSaleRequest());

        $request = $this->httpClient->getLastRequest();

        self::assertNotNull($request);
        self::assertArrayHasKey('PowerTranz-PowerTranzId', $request['headers']);
        self::assertArrayHasKey('PowerTranz-PowerTranzPassword', $request['headers']);
        self::assertSame('test-id', $request['headers']['PowerTranz-PowerTranzId']);
    }

    public function testRequestBodyContainsRequiredFields(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $this->service->sale($this->makeSaleRequest());

        $request = $this->httpClient->getLastRequest();
        $body    = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('TotalAmount', $body);
        self::assertArrayHasKey('CurrencyCode', $body);
        self::assertArrayHasKey('OrderIdentifier', $body);
        self::assertArrayHasKey('Source', $body);
        self::assertSame('840', $body['CurrencyCode']);
    }

    public function testSaleWithThreeDsIncludesThreeDsBlock(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $browserDetails = new BrowserDetails(
            acceptHeader:  '*/*',
            colorDepth:    '24',
            javaEnabled:   false,
            language:      'en-US',
            screenHeight:  900,
            screenWidth:   1440,
            timeZone:      '-300',
            userAgent:     'Mozilla/5.0',
        );

        $request = new SaleRequest(
            totalAmount:           99.00,
            currencyCode:          CurrencyCode::USD,
            orderIdentifier:       'order-3ds',
            transactionIdentifier: 'txn-3ds',
            source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
            threeDSecure:          ThreeDSecure::withBrowser($browserDetails),
        );

        $this->service->sale($request);

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('ThreeDSecure', $body);
        self::assertTrue($body['ThreeDSecure']['Enabled']);
        self::assertArrayHasKey('BrowserDetails', $body['ThreeDSecure']);
    }

    public function testValidationExceptionThrownBeforeHttpCall(): void
    {
        $this->expectException(ValidationException::class);

        // Invalid PAN triggers ValidationException in CardSource constructor — no HTTP call made
        new CardSource('123', '2512', '123', 'Jane Doe');

        self::assertSame(0, $this->httpClient->getRequestCount());
    }

    private function makeSaleRequest(): SaleRequest
    {
        return new SaleRequest(
            totalAmount:           99.50,
            currencyCode:          CurrencyCode::USD,
            orderIdentifier:       'order-456',
            transactionIdentifier: 'txn-sale-001',
            source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );
    }
}
