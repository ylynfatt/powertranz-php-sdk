<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Model\Response;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Enum\IsoResponseCode;
use PowerTranz\Model\Response\SaleResponse;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\Tests\Fixture\ResponseFixture;

final class SpiResponseTest extends TestCase
{
    public function testHydratesApprovedSaleResponse(): void
    {
        $data     = ResponseFixture::loadAsArray('sale_approved');
        $response = SaleResponse::fromArray($data);

        self::assertTrue($response->approved);
        self::assertFalse($response->requiresRedirect);
        self::assertSame(IsoResponseCode::APPROVED, $response->isoResponseCode);
        self::assertSame('txn-sale-001', $response->transactionIdentifier);
        self::assertSame('order-456', $response->orderIdentifier);
        self::assertSame('MASTERCARD', $response->cardBrand);
        self::assertNotNull($response->totalAmount);
        self::assertInstanceOf(Money::class, $response->totalAmount);
        self::assertSame('99.50', (string) $response->totalAmount->getAmount());
        self::assertSame('USD', $response->totalAmount->getCurrency()->getCurrencyCode());
    }

    public function testHydratesDeclinedResponse(): void
    {
        $data     = ResponseFixture::loadAsArray('sale_declined');
        $response = SaleResponse::fromArray($data);

        self::assertFalse($response->approved);
        self::assertSame(IsoResponseCode::DO_NOT_HONOUR, $response->isoResponseCode);
        self::assertTrue($response->isoResponseCode->isDeclined());
    }

    public function testNullPanTokenOnNonTokenizedResponse(): void
    {
        $data     = ResponseFixture::loadAsArray('sale_approved');
        $response = SaleResponse::fromArray($data);

        self::assertNull($response->panToken);
    }

    public function testPanTokenPresentOnTokenizedResponse(): void
    {
        $data     = ResponseFixture::loadAsArray('sale_tokenized');
        $response = SaleResponse::fromArray($data);

        self::assertSame('pan-token-stored-card-xyz', $response->panToken);
    }

    public function testGetRawReturnsArbitraryField(): void
    {
        $data     = ResponseFixture::loadAsArray('sale_approved');
        $response = SaleResponse::fromArray($data);

        self::assertSame('A0000', $response->getRaw('ResponseCode'));
        self::assertNull($response->getRaw('NonExistentField'));
        self::assertSame('default', $response->getRaw('NonExistentField', 'default'));
    }

    /**
     * The gateway names this field RRN, not ReferenceNumber. Reading the wrong
     * key left $referenceNumber permanently null while the README and examples
     * advertised it.
     */
    public function testReferenceNumberIsReadFromRrn(): void
    {
        $response = SaleResponse::fromArray([
            'IsoResponseCode' => '00',
            'RRN'             => '307522590956',
        ]);

        self::assertSame('307522590956', $response->referenceNumber);
    }

    public function testReferenceNumberFallsBackToReferenceNumberKey(): void
    {
        $response = SaleResponse::fromArray([
            'IsoResponseCode' => '00',
            'ReferenceNumber' => 'REF999',
        ]);

        self::assertSame('REF999', $response->referenceNumber);
    }

    public function testReferenceNumberIsNullWhenAbsent(): void
    {
        $response = SaleResponse::fromArray(['IsoResponseCode' => '00']);

        self::assertNull($response->referenceNumber);
    }

    public function testThreeDsChallengeBuildsFromSp4Response(): void
    {
        $data      = ResponseFixture::loadAsArray('sale_3ds_redirect');
        $challenge = ThreeDSecureChallenge::fromArray($data);

        self::assertSame('spi-token-abc123xyz', $challenge->spiToken);
        self::assertSame('txn-3ds-001', $challenge->transactionIdentifier);
        self::assertSame('order-3ds-123', $challenge->orderIdentifier);
        self::assertSame('SPI Preprocessing complete', $challenge->responseMessage);

        // render() returns the gateway document untouched.
        self::assertStringContainsString('threeDSMethodData', $challenge->render());
        self::assertStringStartsWith('<!DOCTYPE html>', $challenge->render());
    }

    public function testIframeWrapsRedirectDataInSrcdoc(): void
    {
        $challenge = ThreeDSecureChallenge::fromArray(
            ResponseFixture::loadAsArray('sale_3ds_redirect')
        );

        $html = $challenge->iframe();

        self::assertStringStartsWith('<iframe srcdoc="', $html);
        self::assertStringEndsWith('></iframe>', $html);
        self::assertStringContainsString('width="100%"', $html);
        self::assertStringContainsString('height="500"', $html);

        // The document must be escaped so it cannot break out of the attribute.
        self::assertStringNotContainsString('<form', $html);
        self::assertStringContainsString('&lt;form', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testIframeDimensionsAreOverridableAndEscaped(): void
    {
        $challenge = ThreeDSecureChallenge::fromArray(
            ResponseFixture::loadAsArray('sale_3ds_redirect')
        );

        self::assertStringContainsString('height="400"', $challenge->iframe('390', '400'));
        self::assertStringContainsString(
            'width="&quot; onload=&quot;alert(1)"',
            $challenge->iframe('" onload="alert(1)'),
        );
    }
}
