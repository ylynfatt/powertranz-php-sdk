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
     * An unrecognised code must not be reported as some other specific code.
     *
     * The previous fallback to DO_NOT_HONOUR meant a gateway 91 ("issuer or
     * switch inoperative" — transient, retryable) surfaced to the merchant as 05,
     * a flat refusal. The raw string is now always preserved.
     */
    public function testUnknownCodeIsNotCoercedToADecline(): void
    {
        $response = SaleResponse::fromArray([
            'IsoResponseCode' => 'ZZ',
            'ResponseMessage' => 'Some future condition',
        ]);

        self::assertNull($response->isoResponseCode);
        self::assertSame('ZZ', $response->isoResponseCodeValue);
        self::assertNotSame('05', $response->isoResponseCodeValue);

        // Fails closed: unknown is never approved, never a pending redirect.
        self::assertFalse($response->approved);
        self::assertFalse($response->requiresRedirect);
    }

    /**
     * The regression this came from: 91 now resolves instead of becoming 05.
     */
    public function testIssuerInoperativeResolvesWithItsOwnMessage(): void
    {
        $response = SaleResponse::fromArray([
            'IsoResponseCode' => '91',
            'ResponseMessage' => 'Issuer or Switch not available. Please try again later.',
        ]);

        self::assertSame(IsoResponseCode::ISSUER_OR_SWITCH_INOPERATIVE, $response->isoResponseCode);
        self::assertSame('91', $response->isoResponseCodeValue);
        self::assertSame('Issuer or switch inoperative', $response->isoResponseCode->label());
        self::assertTrue($response->isoResponseCode->isRetryable());
        self::assertFalse($response->approved);
    }

    public function testRawCodeIsPreservedForKnownCodesToo(): void
    {
        $response = SaleResponse::fromArray(['IsoResponseCode' => '00']);

        self::assertSame('00', $response->isoResponseCodeValue);
        self::assertSame(IsoResponseCode::APPROVED, $response->isoResponseCode);
        self::assertTrue($response->approved);
    }

    public function testMissingCodeYieldsNullEnumAndEmptyRaw(): void
    {
        $response = SaleResponse::fromArray(['ResponseMessage' => 'nothing']);

        self::assertNull($response->isoResponseCode);
        self::assertSame('', $response->isoResponseCodeValue);
        self::assertFalse($response->approved);
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

    /**
     * The challenge must retain the code that produced it, so callers can tell
     * an SPI redirect (SP4) from a hosted-page one (HP0) and log what the
     * gateway actually said.
     */
    public function testChallengeRetainsIsoResponseCode(): void
    {
        $challenge = ThreeDSecureChallenge::fromArray(
            ResponseFixture::loadAsArray('sale_3ds_redirect')
        );

        self::assertSame(IsoResponseCode::SPI_PREPROCESSING_COMPLETE, $challenge->isoResponseCode);
        self::assertSame('SP4', $challenge->isoResponseCode->value);
    }

    public function testChallengeRetainsHostedPageCode(): void
    {
        $challenge = ThreeDSecureChallenge::fromArray([
            'SpiToken'        => 't',
            'RedirectData'    => '<html></html>',
            'IsoResponseCode' => 'HP0',
        ]);

        self::assertSame(IsoResponseCode::HPP_PREPROCESSING_COMPLETE, $challenge->isoResponseCode);
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
