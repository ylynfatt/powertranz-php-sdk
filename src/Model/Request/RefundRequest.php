<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\Address;

/**
 * Refund a previously captured transaction, in full or partially.
 *
 * Corresponds to POST /refund.
 */
final class RefundRequest implements JsonSerializable
{
    public function __construct(
        public readonly string $transactionIdentifier,
        public readonly float $totalAmount,
        public readonly CurrencyCode $currencyCode,
        public readonly string $orderIdentifier,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
        public readonly ?Address $billingAddress = null,
    ) {
        $errors = [];

        if (trim($this->transactionIdentifier) === '') {
            $errors['transactionIdentifier'] = 'TransactionIdentifier must not be empty.';
        }

        if ($this->totalAmount <= 0) {
            $errors['totalAmount'] = 'TotalAmount must be greater than zero.';
        }

        if (trim($this->orderIdentifier) === '') {
            $errors['orderIdentifier'] = 'OrderIdentifier must not be empty.';
        }

        if ($errors !== []) {
            throw new ValidationException('Refund request validation failed.', $errors);
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TransactionIdentifier' => $this->transactionIdentifier,
            'TotalAmount'           => round($this->totalAmount, 2),
            'CurrencyCode'          => $this->currencyCode->numericString(),
            'OrderIdentifier'       => $this->orderIdentifier,
            'Refund'                => true,
        ];

        if ($this->externalIdentifier !== null) {
            $data['ExternalIdentifier'] = $this->externalIdentifier;
        }

        if ($this->externalGroupIdentifier !== null) {
            $data['ExternalGroupIdentifier'] = $this->externalGroupIdentifier;
        }

        if ($this->billingAddress !== null) {
            $data['BillingAddress'] = $this->billingAddress->jsonSerialize();
        }

        return $data;
    }
}
