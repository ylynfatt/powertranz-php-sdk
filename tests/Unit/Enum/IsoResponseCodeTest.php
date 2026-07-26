<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use PowerTranz\Enum\IsoResponseCode;

final class IsoResponseCodeTest extends TestCase
{
    public function testApprovedIsApprovedOnly(): void
    {
        $code = IsoResponseCode::APPROVED;

        self::assertTrue($code->isApproved());
        self::assertFalse($code->requiresRedirect());
        self::assertFalse($code->isNonFinancialSuccess());
        self::assertFalse($code->isDeclined());
    }

    public function testDeclinedIsDeclined(): void
    {
        $code = IsoResponseCode::DO_NOT_HONOUR;

        self::assertFalse($code->isApproved());
        self::assertFalse($code->requiresRedirect());
        self::assertTrue($code->isDeclined());
    }

    /**
     * SP4 — not 3D0 — is what the first SPI call returns alongside RedirectData.
     * This is the code that must drive the redirect branch.
     */
    public function testSp4RequiresRedirect(): void
    {
        $code = IsoResponseCode::SPI_PREPROCESSING_COMPLETE;

        self::assertSame('SP4', $code->value);
        self::assertTrue($code->requiresRedirect());
        self::assertFalse($code->isApproved());
        self::assertFalse($code->isDeclined());
    }

    public function testHp0RequiresRedirect(): void
    {
        $code = IsoResponseCode::HPP_PREPROCESSING_COMPLETE;

        self::assertSame('HP0', $code->value);
        self::assertTrue($code->requiresRedirect());
        self::assertFalse($code->isDeclined());
    }

    /**
     * 3D0 means "3DS complete" and is delivered to the MerchantResponseUrl
     * callback. It must NOT trigger the redirect branch.
     */
    public function testThreeDsCompleteDoesNotRequireRedirect(): void
    {
        $code = IsoResponseCode::THREE_DS_COMPLETE;

        self::assertSame('3D0', $code->value);
        self::assertFalse($code->requiresRedirect());
        self::assertTrue($code->isNonFinancialSuccess());
        self::assertFalse($code->isDeclined());
    }

    /**
     * 3D1 is "3DS not supported" — a success the caller may proceed from,
     * not an authentication completion and not a decline.
     */
    public function testThreeDsNotSupportedIsNotADecline(): void
    {
        $code = IsoResponseCode::THREE_DS_NOT_SUPPORTED;

        self::assertSame('3D1', $code->value);
        self::assertTrue($code->isNonFinancialSuccess());
        self::assertFalse($code->isDeclined());
        self::assertFalse($code->isApproved());
    }

    /**
     * @return list<array{string, string}>
     */
    public static function documentedGatewayCodes(): array
    {
        return [
            ['SP4', 'SPI preprocessing complete'],
            ['HP0', 'HPP preprocessing complete'],
            ['3D0', '3DS complete'],
            ['3D1', '3DS not supported'],
            ['TK0', 'Tokenize complete'],
            ['FC0', 'Fraud check complete'],
            ['3D4', 'Authenticate payer'],
            ['3D5', 'Fingerprint payer'],
            ['3D6', 'Challenge payer'],
        ];
    }

    /**
     * Every code from the published "Approved or completed transactions" table
     * must resolve, with the documented response message as its label.
     *
     * @dataProvider documentedGatewayCodes
     */
    public function testDocumentedGatewayCodesResolve(string $value, string $label): void
    {
        $code = IsoResponseCode::tryFrom($value);

        self::assertNotNull($code, "Gateway code {$value} is missing from the enum");
        self::assertSame($label, $code->label());
    }

    public function testTokenizeAndFraudCheckAreNonFinancialSuccesses(): void
    {
        self::assertTrue(IsoResponseCode::TOKENIZE_COMPLETE->isNonFinancialSuccess());
        self::assertTrue(IsoResponseCode::FRAUD_CHECK_COMPLETE->isNonFinancialSuccess());
        self::assertFalse(IsoResponseCode::TOKENIZE_COMPLETE->isDeclined());
        self::assertFalse(IsoResponseCode::FRAUD_CHECK_COMPLETE->isDeclined());
    }

    /**
     * FPI-only codes resolve, but on an SPI flow they mean the transaction
     * stalled — so they count as declines rather than successes.
     */
    public function testFpiPayerCodesAreDeclinesOnSpi(): void
    {
        foreach ([
            IsoResponseCode::AUTHENTICATE_PAYER,
            IsoResponseCode::FINGERPRINT_PAYER,
            IsoResponseCode::CHALLENGE_PAYER,
        ] as $code) {
            self::assertTrue($code->isDeclined(), "{$code->value} should not read as success on SPI");
            self::assertFalse($code->requiresRedirect());
        }
    }

    public function testFromStringReturnsCorrectCase(): void
    {
        self::assertSame(IsoResponseCode::APPROVED, IsoResponseCode::from('00'));
        self::assertSame(IsoResponseCode::THREE_DS_COMPLETE, IsoResponseCode::from('3D0'));
        self::assertSame(IsoResponseCode::SPI_PREPROCESSING_COMPLETE, IsoResponseCode::from('SP4'));
        self::assertSame(IsoResponseCode::INSUFFICIENT_FUNDS, IsoResponseCode::from('51'));
    }

    public function testTryFromReturnsNullForUnknownCode(): void
    {
        self::assertNull(IsoResponseCode::tryFrom('ZZZ'));
    }

    public function testLabelReturnsHumanReadableString(): void
    {
        self::assertSame('Transaction is approved', IsoResponseCode::APPROVED->label());
        self::assertSame('SPI preprocessing complete', IsoResponseCode::SPI_PREPROCESSING_COMPLETE->label());
    }
}
