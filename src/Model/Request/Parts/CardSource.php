<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Exception\ValidationException;

/**
 * Card payment source — use when charging a raw card number.
 *
 * For repeat/recurring charges use {@see TokenSource} with the PanToken
 * returned from a previous transaction.
 */
final class CardSource implements JsonSerializable
{
    public function __construct(
        public readonly string $cardPan,
        public readonly string $cardExpiration,
        public readonly string $cardCvv,
        public readonly string $cardholderName,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $errors = [];

        if (!preg_match('/^\d{12,19}$/', $this->cardPan)) {
            $errors['cardPan'] = 'CardPan must be 12–19 digits.';
        }

        if (!preg_match('/^\d{4}$/', $this->cardExpiration)) {
            $errors['cardExpiration'] = 'CardExpiration must be in YYMM format (e.g. 2512 for December 2025).';
        }

        if (!preg_match('/^\d{3,4}$/', $this->cardCvv)) {
            $errors['cardCvv'] = 'CardCvv must be 3 or 4 digits.';
        }

        $nameLength = strlen($this->cardholderName);

        if ($nameLength < 2 || $nameLength > 45) {
            $errors['cardholderName'] = 'CardholderName must be between 2 and 45 characters.';
        }

        if ($errors !== []) {
            throw new ValidationException('Card source validation failed.', $errors);
        }
    }

    /**
     * Returns a masked PAN showing the BIN (first 6) and last 4 digits.
     * Example: 4111111111111111 → 411111******1111
     */
    public function maskedPan(): string
    {
        $len = strlen($this->cardPan);

        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        return substr($this->cardPan, 0, 6)
            . str_repeat('*', $len - 10)
            . substr($this->cardPan, -4);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'CardPan'        => $this->cardPan,
            'CardExpiration' => $this->cardExpiration,
            'CardCvv'        => $this->cardCvv,
            'CardholderName' => $this->cardholderName,
        ];
    }
}
