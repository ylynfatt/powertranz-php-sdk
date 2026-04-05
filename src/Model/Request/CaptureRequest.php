<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Exception\ValidationException;

/**
 * Capture previously authorised funds.
 *
 * The captured amount may be less than or equal to the authorised amount.
 * Partial captures are supported.
 *
 * Corresponds to POST /capture.
 */
final class CaptureRequest implements JsonSerializable
{
    public function __construct(
        public readonly string $transactionIdentifier,
        public readonly float $totalAmount,
        public readonly ?float $tipAmount = null,
        public readonly ?float $taxAmount = null,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
    ) {
        $errors = [];

        if (trim($this->transactionIdentifier) === '') {
            $errors['transactionIdentifier'] = 'TransactionIdentifier must not be empty.';
        }

        if ($this->totalAmount <= 0) {
            $errors['totalAmount'] = 'TotalAmount must be greater than zero.';
        }

        if ($errors !== []) {
            throw new ValidationException('Capture request validation failed.', $errors);
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TransactionIdentifier' => $this->transactionIdentifier,
            'TotalAmount'           => round($this->totalAmount, 2),
        ];

        if ($this->tipAmount !== null) {
            $data['TipAmount'] = round($this->tipAmount, 2);
        }

        if ($this->taxAmount !== null) {
            $data['TaxAmount'] = round($this->taxAmount, 2);
        }

        if ($this->externalIdentifier !== null) {
            $data['ExternalIdentifier'] = $this->externalIdentifier;
        }

        if ($this->externalGroupIdentifier !== null) {
            $data['ExternalGroupIdentifier'] = $this->externalGroupIdentifier;
        }

        return $data;
    }
}
