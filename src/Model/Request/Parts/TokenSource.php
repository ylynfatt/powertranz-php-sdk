<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Exception\ValidationException;

/**
 * Token-based payment source — use the PanToken returned from a previous
 * transaction to charge a stored card without re-entering card details.
 */
final class TokenSource implements JsonSerializable
{
    public function __construct(
        public readonly string $panToken,
        public readonly ?string $cardCvv = null,
    ) {
        if (trim($this->panToken) === '') {
            throw new ValidationException('PanToken must not be empty.', ['panToken' => 'PanToken is required.']);
        }

        if ($this->cardCvv !== null && !preg_match('/^\d{3,4}$/', $this->cardCvv)) {
            throw new ValidationException('Invalid CVV.', ['cardCvv' => 'CardCvv must be 3 or 4 digits.']);
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = ['PanToken' => $this->panToken];

        if ($this->cardCvv !== null) {
            $data['CardCvv'] = $this->cardCvv;
        }

        return $data;
    }
}
