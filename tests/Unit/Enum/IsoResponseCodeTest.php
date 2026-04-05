<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use PowerTranz\Enum\IsoResponseCode;

final class IsoResponseCodeTest extends TestCase
{
    public function testApprovedIsApproved(): void
    {
        $code = IsoResponseCode::APPROVED;

        self::assertTrue($code->isApproved());
        self::assertFalse($code->requires3dsChallenge());
        self::assertFalse($code->isDeclined());
    }

    public function testDeclinedIsDeclined(): void
    {
        $code = IsoResponseCode::DO_NOT_HONOUR;

        self::assertFalse($code->isApproved());
        self::assertFalse($code->requires3dsChallenge());
        self::assertTrue($code->isDeclined());
    }

    public function testThreeDsRedirectRequiresChallenge(): void
    {
        $code = IsoResponseCode::THREE_DS_REDIRECT;

        self::assertFalse($code->isApproved());
        self::assertTrue($code->requires3dsChallenge());
        self::assertFalse($code->isDeclined());
    }

    public function testFromStringReturnsCorrectCase(): void
    {
        self::assertSame(IsoResponseCode::APPROVED, IsoResponseCode::from('00'));
        self::assertSame(IsoResponseCode::THREE_DS_REDIRECT, IsoResponseCode::from('3D0'));
        self::assertSame(IsoResponseCode::INSUFFICIENT_FUNDS, IsoResponseCode::from('51'));
    }

    public function testTryFromReturnsNullForUnknownCode(): void
    {
        self::assertNull(IsoResponseCode::tryFrom('ZZZ'));
    }

    public function testLabelReturnsHumanReadableString(): void
    {
        self::assertSame('Approved', IsoResponseCode::APPROVED->label());
        self::assertSame('3DS challenge required', IsoResponseCode::THREE_DS_REDIRECT->label());
    }
}
