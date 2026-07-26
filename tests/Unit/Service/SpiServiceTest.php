<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Service;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Enum\IsoResponseCode;
use PowerTranz\Exception\AuthenticationException;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ExtendedData;
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
use Ramsey\Uuid\Uuid;

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

    /**
     * The first SPI call returns SP4 ("SPI Preprocessing complete") with
     * RedirectData and an SpiToken — this is what must produce a challenge.
     */
    public function testSaleReturnsThreeDsChallengeOnSp4(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $result = $this->service->sale($this->makeSaleRequest());

        self::assertInstanceOf(ThreeDSecureChallenge::class, $result);
        self::assertSame('spi-token-abc123xyz', $result->spiToken);
        self::assertStringContainsString('threeDSMethodData', $result->redirectHtml);
    }

    /**
     * 3D0 is the authentication result delivered to the MerchantResponseUrl,
     * not a pending redirect. Reaching it on a sale response means the flow is
     * already past the challenge, so it must hydrate as a normal SaleResponse.
     */
    public function testThreeDsCompleteCodeIsNotTreatedAsAChallenge(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('threeds_authentication_result'));

        $result = $this->service->sale($this->makeSaleRequest());

        self::assertInstanceOf(SaleResponse::class, $result);
        self::assertNotInstanceOf(ThreeDSecureChallenge::class, $result);
        self::assertFalse($result->requiresRedirect);
        self::assertSame(IsoResponseCode::THREE_DS_COMPLETE, $result->isoResponseCode);
    }

    public function testAuthorizeReturnsAuthResponse(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('auth_approved'));

        $request = new AuthRequest(
            totalAmount:     Money::of('29.99', 'USD'),
            orderIdentifier: 'order-123',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
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
        self::assertArrayHasKey('TransactionIdentifier', $body);
        self::assertArrayHasKey('Source', $body);
        self::assertSame('840', $body['CurrencyCode']);
    }

    public function testTransactionIdentifierIsAutoGeneratedAsUuid(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        // No transactionIdentifier supplied
        $request = new SaleRequest(
            totalAmount:     Money::of('10.00', 'USD'),
            orderIdentifier: 'order-auto-uuid',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );

        self::assertTrue(
            Uuid::isValid($request->transactionIdentifier),
            "Expected auto-generated transactionIdentifier to be a valid UUID, got: {$request->transactionIdentifier}"
        );

        $this->service->sale($request);

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue(Uuid::isValid($body['TransactionIdentifier']));
    }

    public function testTwoRequestsWithoutTransactionIdentifierGetDifferentUuids(): void
    {
        $source = new CardSource('4111111111111111', '2512', '123', 'Jane Doe');

        $request1 = new SaleRequest(Money::of('10.00', 'USD'), 'order-1', $source);
        $request2 = new SaleRequest(Money::of('10.00', 'USD'), 'order-2', $source);

        self::assertNotSame($request1->transactionIdentifier, $request2->transactionIdentifier);
    }

    public function testCallerSuppliedUuidIsPreserved(): void
    {
        $customUuid = '550e8400-e29b-41d4-a716-446655440000';

        $request = new SaleRequest(
            totalAmount:           Money::of('10.00', 'USD'),
            orderIdentifier:       'order-custom',
            source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
            transactionIdentifier: $customUuid,
        );

        self::assertSame($customUuid, $request->transactionIdentifier);
    }

    public function testInvalidTransactionIdentifierThrowsValidationException(): void
    {
        try {
            new SaleRequest(
                totalAmount:           Money::of('10.00', 'USD'),
                orderIdentifier:       'order-bad-id',
                source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
                transactionIdentifier: 'not-a-uuid',
            );
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('transactionIdentifier', $e->getErrors());
            self::assertStringContainsString(
                'valid UUID',
                $e->getErrors()['transactionIdentifier'],
            );
        }
    }

    /**
     * The wire format for a 3DS sale, asserted field by field against the
     * payload published in the SPI-3DS integration guide: ThreeDSecure is a
     * top-level boolean, and the parameters plus the callback URL sit under
     * ExtendedData.
     */
    public function testThreeDsSaleMatchesDocumentedPayload(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $request = new SaleRequest(
            totalAmount:     Money::of('99.00', 'USD'),
            orderIdentifier: 'order-3ds',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
            threeDSecure:    true,
            extendedData:    ExtendedData::forThreeDSecure(
                merchantResponseUrl: 'https://merchant.example.com/3ds/callback',
                threeDSecure:        new ThreeDSecure(
                    challengeWindowSize: ThreeDSecure::WINDOW_600x400,
                    challengeIndicator:  ThreeDSecure::CHALLENGE_NO_PREFERENCE,
                ),
            ),
        );

        $this->service->sale($request);

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        // Top-level flag is a boolean, not an object.
        self::assertTrue($body['ThreeDSecure']);
        self::assertIsBool($body['ThreeDSecure']);

        self::assertSame(
            [
                'ThreeDSecure' => [
                    'ChallengeWindowSize' => 4,
                    'ChallengeIndicator'  => '01',
                ],
                'MerchantResponseUrl' => 'https://merchant.example.com/3ds/callback',
            ],
            $body['ExtendedData'],
        );

        // ChallengeWindowSize is an integer on the wire, never a padded string.
        self::assertIsInt($body['ExtendedData']['ThreeDSecure']['ChallengeWindowSize']);
    }

    public function testNonThreeDsSaleSendsFalseAndOmitsExtendedData(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $this->service->sale($this->makeSaleRequest());

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($body['ThreeDSecure']);
        self::assertArrayNotHasKey('ExtendedData', $body);
    }

    /**
     * Enabling 3DS without ExtendedData means no MerchantResponseUrl, so the
     * gateway would have nowhere to return the cardholder. Caught locally.
     */
    public function testThreeDsWithoutExtendedDataIsRejectedLocally(): void
    {
        try {
            new SaleRequest(
                totalAmount:     Money::of('99.00', 'USD'),
                orderIdentifier: 'order-3ds',
                source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
                threeDSecure:    true,
            );
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('extendedData', $e->getErrors());
            self::assertStringContainsString('MerchantResponseUrl', $e->getErrors()['extendedData']);
        }

        self::assertSame(0, $this->httpClient->getRequestCount());
    }

    public function testMerchantResponseUrlMustBeAValidUrl(): void
    {
        try {
            ExtendedData::forThreeDSecure('not-a-url');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('merchantResponseUrl', $e->getErrors());
        }
    }

    public function testChallengeWindowSizeIsConstrainedToDocumentedRange(): void
    {
        $this->expectException(ValidationException::class);

        new ThreeDSecure(challengeWindowSize: 6);
    }

    public function testValidationExceptionThrownBeforeHttpCall(): void
    {
        $this->expectException(ValidationException::class);

        // Invalid PAN triggers ValidationException in CardSource constructor — no HTTP call made
        new CardSource('123', '2512', '123', 'Jane Doe');

        self::assertSame(0, $this->httpClient->getRequestCount());
    }

    public function testCurrencyCodeEnumMoneyFactory(): void
    {
        $money = CurrencyCode::USD->money('29.99');

        self::assertSame('USD', $money->getCurrency()->getCurrencyCode());
        self::assertSame('29.99', (string) $money->getAmount());
    }

    public function testCurrencyCodeIsoAlpha(): void
    {
        self::assertSame('USD', CurrencyCode::USD->isoAlpha());
        self::assertSame('XCD', CurrencyCode::XCD->isoAlpha());
        self::assertSame('JMD', CurrencyCode::JMD->isoAlpha());
    }

    public function testCurrencyCodeFromAlphaCode(): void
    {
        self::assertSame(CurrencyCode::USD, CurrencyCode::fromAlphaCode('USD'));
        self::assertSame(CurrencyCode::XCD, CurrencyCode::fromAlphaCode('XCD'));
        self::assertSame(CurrencyCode::USD, CurrencyCode::fromAlphaCode('usd')); // case-insensitive
    }

    public function testTotalAmountInBodyMatchesMoneyAmount(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_approved'));

        $this->service->sale(new SaleRequest(
            totalAmount:     Money::of('149.99', 'USD'),
            orderIdentifier: 'order-amount-check',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        ));

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(149.99, $body['TotalAmount']);
    }

    private function makeSaleRequest(): SaleRequest
    {
        return new SaleRequest(
            totalAmount:     Money::of('99.50', 'USD'),
            orderIdentifier: 'order-456',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );
    }
}
