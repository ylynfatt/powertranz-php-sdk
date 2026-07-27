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
        self::assertSame('Approved', IsoResponseCode::APPROVED->label());
        self::assertSame('SPI preprocessing complete', IsoResponseCode::SPI_PREPROCESSING_COMPLETE->label());
    }

    /**
     * 91 means the issuer or switch was unreachable — a transient condition worth
     * retrying, and materially different from a flat 05 refusal. It was missing
     * from the enum entirely, and unknown codes were being coerced to 05, so a
     * real 91 was reported to merchants as "do not honour".
     */
    public function testIssuerInoperativeIsDistinctFromDoNotHonour(): void
    {
        $inoperative = IsoResponseCode::from('91');

        self::assertSame(IsoResponseCode::ISSUER_OR_SWITCH_INOPERATIVE, $inoperative);
        self::assertSame('Issuer or switch inoperative', $inoperative->label());
        self::assertTrue($inoperative->isRetryable());

        self::assertFalse(IsoResponseCode::DO_NOT_HONOUR->isRetryable());
        self::assertNotSame($inoperative, IsoResponseCode::DO_NOT_HONOUR);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function documentedFinancialCodes(): array
    {
        return [
            ['00', 'Approved'],
            ['01', 'Refer to issuer'],
            ['05', 'Do not honor'],
            ['12', 'Invalid transaction'],
            ['51', 'Not sufficient funds'],
            ['54', 'Expired card'],
            ['57', 'Transaction not permitted to card'],
            ['82', 'Incorrect CVV (Visa/Amex), Policy (MC)'],
            ['91', 'Issuer or switch inoperative'],
            ['96', 'System malfunction'],
            ['98', 'Host Unreachable'],
            ['N7', 'Decline for CVV2 failure'],
            ['P6', 'Unsafe PIN'],
            ['XA', 'Forward to issuer'],
        ];
    }

    /**
     * @dataProvider documentedFinancialCodes
     */
    public function testDocumentedFinancialCodesResolve(string $value, string $label): void
    {
        $code = IsoResponseCode::tryFrom($value);

        self::assertNotNull($code, "Financial code {$value} is missing from the enum");
        self::assertSame($label, $code->label());
    }

    /**
     * The published table has 92 financial codes. Guards against a partial list
     * silently reappearing.
     */
    public function testEveryDocumentedFinancialCodeIsPresent(): void
    {
        $documented = [
            '00','01','02','03','04','05','06','07','08','09','10','11','12','13','14','15','16',
            '17','18','19','20','21','22','23','24','25','26','27','28','29','30','31','32','33',
            '34','35','36','37','38','39','40','41','42','43','44','51','52','53','54','55','56',
            '57','58','59','60','61','62','63','64','65','66','67','68','75','76','77','80','81',
            '82','83','84','85','86','88','89','90','91','92','93','94','95','96','97','98','99',
            'N0','N3','N4','N7','P2','P5','P6','XA','XD',
        ];

        $missing = array_values(array_filter(
            $documented,
            static fn (string $code): bool => IsoResponseCode::tryFrom($code) === null,
        ));

        self::assertSame([], $missing, 'Codes missing from the enum: ' . implode(', ', $missing));
    }

    public function testRetryableCodesAreTransientOnly(): void
    {
        foreach (['91', '96', '98', '90', '92'] as $retryable) {
            self::assertTrue(IsoResponseCode::from($retryable)->isRetryable(), "{$retryable} should be retryable");
        }

        foreach (['05', '51', '54', '63', '14'] as $terminal) {
            self::assertFalse(IsoResponseCode::from($terminal)->isRetryable(), "{$terminal} should not be retryable");
        }
    }

    public function testCardRetentionCodes(): void
    {
        foreach (['04', '07', '41', '43', '67'] as $retain) {
            self::assertTrue(IsoResponseCode::from($retain)->requiresCardRetention(), "{$retain} should retain");
        }

        self::assertFalse(IsoResponseCode::DO_NOT_HONOUR->requiresCardRetention());
    }

    /**
     * Only 00 is an unqualified approval; the partial/VIP approvals are not.
     */
    public function testOnlyZeroZeroIsApproved(): void
    {
        self::assertTrue(IsoResponseCode::APPROVED->isApproved());

        foreach (['10', '11', '16', '32'] as $qualified) {
            self::assertFalse(IsoResponseCode::from($qualified)->isApproved());
        }
    }
}
