<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Enum;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\SaleRequest;

final class CurrencyCodeTest extends TestCase
{
    /**
     * ISO 4217 numeric codes are always three digits. Four of the cases here are
     * written unpadded because PHP reads `052` as octal 42, so the padding has to
     * be restored on the way out.
     *
     * The gateway does not tolerate the short form: a BBD sale sent as "52" comes
     * back with IsoResponseCode 97, "Request failed validation", and an Errors
     * entry of code 38, "Field is invalid: CurrencyCode".
     */
    public function testEveryCodeRendersAsThreeDigits(): void
    {
        foreach (CurrencyCode::cases() as $case) {
            self::assertSame(
                3,
                strlen($case->numericString()),
                "{$case->name} must render as three digits, got '{$case->numericString()}'",
            );
        }
    }

    /**
     * @dataProvider leadingZeroCodes
     */
    public function testCodesWithALeadingZeroArePadded(CurrencyCode $case, string $expected): void
    {
        self::assertSame($expected, $case->numericString());
    }

    /**
     * @return array<string, array{CurrencyCode, string}>
     */
    public static function leadingZeroCodes(): array
    {
        return [
            'BBD' => [CurrencyCode::BBD, '052'],
            'BSD' => [CurrencyCode::BSD, '044'],
            'BZD' => [CurrencyCode::BZD, '084'],
            'AUD' => [CurrencyCode::AUD, '036'],
        ];
    }

    /**
     * Padding must not disturb the codes that were already three digits.
     */
    public function testThreeDigitCodesAreUnchanged(): void
    {
        self::assertSame('840', CurrencyCode::USD->numericString());
        self::assertSame('388', CurrencyCode::JMD->numericString());
        self::assertSame('951', CurrencyCode::XCD->numericString());
        self::assertSame('136', CurrencyCode::KYD->numericString());
    }

    /**
     * The padding is only useful if it survives to the request body, which is
     * where the gateway rejected it.
     */
    public function testPaddedCodeReachesTheRequestPayload(): void
    {
        $request = new SaleRequest(
            totalAmount:     Money::of('10.00', 'BBD'),
            orderIdentifier: 'order-bbd-1',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );

        $body = $request->jsonSerialize();

        self::assertSame('052', $body['CurrencyCode']);
    }

    /**
     * Responses quote the code in either form depending on the endpoint, and the
     * int cast in SpiResponse handles both — so the round trip has to hold.
     */
    public function testRoundTripFromPaddedString(): void
    {
        foreach (CurrencyCode::cases() as $case) {
            self::assertSame(
                $case,
                CurrencyCode::from((int) $case->numericString()),
                "{$case->name} must survive a round trip through its numeric string",
            );
        }
    }
}
