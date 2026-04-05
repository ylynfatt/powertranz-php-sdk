<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use JsonSerializable;
use PowerTranz\Exception\ValidationException;

/**
 * Capture previously authorised funds.
 *
 * The captured amount may be less than or equal to the authorised amount.
 * Partial captures are supported.
 *
 * All monetary values are expressed as {@see Money} objects to eliminate
 * float rounding issues.  The tip and tax amounts, when provided, must use
 * the same currency as the capture amount.
 *
 * Corresponds to POST /capture.
 */
final class CaptureRequest implements JsonSerializable
{
    public function __construct(
        public readonly string $transactionIdentifier,
        public readonly Money $totalAmount,
        public readonly ?Money $tipAmount = null,
        public readonly ?Money $taxAmount = null,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
    ) {
        $errors = [];

        if (trim($this->transactionIdentifier) === '') {
            $errors['transactionIdentifier'] = 'TransactionIdentifier must not be empty.';
        }

        if (!$this->totalAmount->isPositive()) {
            $errors['totalAmount'] = 'TotalAmount must be greater than zero.';
        }

        $currency = $this->totalAmount->getCurrency();

        if ($this->tipAmount !== null && !$this->tipAmount->getCurrency()->is($currency)) {
            $errors['tipAmount'] = 'TipAmount currency must match TotalAmount currency.';
        }

        if ($this->taxAmount !== null && !$this->taxAmount->getCurrency()->is($currency)) {
            $errors['taxAmount'] = 'TaxAmount currency must match TotalAmount currency.';
        }

        if ($errors !== []) {
            throw new ValidationException('Capture request validation failed.', $errors);
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TransactionIdentifier' => $this->transactionIdentifier,
            'TotalAmount'           => (float) (string) $this->totalAmount->getAmount(),
        ];

        if ($this->tipAmount !== null) {
            $data['TipAmount'] = (float) (string) $this->tipAmount->getAmount();
        }

        if ($this->taxAmount !== null) {
            $data['TaxAmount'] = (float) (string) $this->taxAmount->getAmount();
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
