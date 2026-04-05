<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Model\Request;

use PHPUnit\Framework\TestCase;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\CardSource;

final class CardSourceTest extends TestCase
{
    public function testValidCardSourceConstructs(): void
    {
        $source = new CardSource('4111111111111111', '2512', '123', 'Jane Doe');

        self::assertSame('4111111111111111', $source->cardPan);
        self::assertSame('2512', $source->cardExpiration);
        self::assertSame('123', $source->cardCvv);
        self::assertSame('Jane Doe', $source->cardholderName);
    }

    public function testMaskedPanHidesMiddleDigits(): void
    {
        $source = new CardSource('4111111111111111', '2512', '123', 'Jane Doe');

        self::assertSame('411111******1111', $source->maskedPan());
    }

    public function testJsonSerializeReturnsCorrectKeys(): void
    {
        $source = new CardSource('4111111111111111', '2512', '456', 'John Smith');
        $json   = $source->jsonSerialize();

        self::assertArrayHasKey('CardPan', $json);
        self::assertArrayHasKey('CardExpiration', $json);
        self::assertArrayHasKey('CardCvv', $json);
        self::assertArrayHasKey('CardholderName', $json);
        self::assertSame('4111111111111111', $json['CardPan']);
    }

    public function testThrowsOnInvalidPan(): void
    {
        $this->expectException(ValidationException::class);

        new CardSource('123', '2512', '123', 'Jane Doe');
    }

    public function testThrowsOnInvalidExpiry(): void
    {
        $this->expectException(ValidationException::class);

        new CardSource('4111111111111111', '12/25', '123', 'Jane Doe');
    }

    public function testThrowsOnInvalidCvv(): void
    {
        $this->expectException(ValidationException::class);

        new CardSource('4111111111111111', '2512', '12', 'Jane Doe');
    }

    public function testThrowsOnTooShortCardholderName(): void
    {
        $this->expectException(ValidationException::class);

        new CardSource('4111111111111111', '2512', '123', 'J');
    }

    public function testAcceptsFourDigitCvv(): void
    {
        $source = new CardSource('378282246310005', '2512', '1234', 'Amex Holder');

        self::assertSame('1234', $source->cardCvv);
    }
}
