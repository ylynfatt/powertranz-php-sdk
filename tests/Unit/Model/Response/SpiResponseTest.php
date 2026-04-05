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
        self::assertFalse($response->requiresThreeDsChallenge);
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

    public function testThreeDsChallengeBuildsFromRedirectResponse(): void
    {
        $data      = ResponseFixture::loadAsArray('sale_3ds_redirect');
        $challenge = ThreeDSecureChallenge::fromArray($data);

        self::assertSame('spi-token-abc123xyz', $challenge->spiToken);
        self::assertSame('txn-3ds-001', $challenge->transactionIdentifier);
        self::assertTrue($challenge->isIframe());
        self::assertStringContainsString('<iframe', $challenge->render());
    }
}
