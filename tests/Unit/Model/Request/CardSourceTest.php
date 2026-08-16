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

    /**
     * PCRE's `$` also matches immediately before a trailing newline, so the
     * digit patterns would otherwise accept "123\n" — and card fields come
     * straight from posted form data, where a stray newline is ordinary.
     *
     * @dataProvider digitFields
     * @param  array{string, string, string, string} $arguments
     */
    public function testTrailingNewlineIsRejected(string $field, array $arguments): void
    {
        try {
            new CardSource(...$arguments);
            self::fail("Expected ValidationException for {$field} with a trailing newline");
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->getErrors());
        }
    }

    /**
     * @return array<string, array{string, array{string, string, string, string}}>
     */
    public static function digitFields(): array
    {
        return [
            'cardPan'        => ['cardPan', ["4111111111111111\n", '2512', '123', 'Jane Doe']],
            'cardExpiration' => ['cardExpiration', ['4111111111111111', "2512\n", '123', 'Jane Doe']],
            'cardCvv'        => ['cardCvv', ['4111111111111111', '2512', "123\n", 'Jane Doe']],
        ];
    }

    /**
     * A PAN carrying a trailing newline is one byte longer than it looks, and
     * both maskedPan() and the SDK's log redaction slice it by offset — so the
     * value must never get past the constructor in the first place.
     */
    public function testMaskedPanIsNeverFedAnUnvalidatedLength(): void
    {
        $this->expectException(ValidationException::class);

        new CardSource("4111111111111111\n", '2512', '123', 'Jane Doe');
    }
}
