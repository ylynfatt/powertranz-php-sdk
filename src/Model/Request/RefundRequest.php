<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use JsonSerializable;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\Address;

/**
 * Refund a previously captured transaction, in full or partially.
 *
 * The {@see $totalAmount} is expressed as a {@see Money} object — the currency
 * is derived from it automatically, so no separate {@see CurrencyCode} parameter
 * is needed.
 *
 * Corresponds to POST /refund.
 */
final class RefundRequest implements JsonSerializable
{
    public function __construct(
        public readonly string $transactionIdentifier,
        public readonly Money $totalAmount,
        public readonly string $orderIdentifier,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
        public readonly ?Address $billingAddress = null,
    ) {
        $errors = [];

        if (trim($this->transactionIdentifier) === '') {
            $errors['transactionIdentifier'] = 'TransactionIdentifier must not be empty.';
        }

        if (!$this->totalAmount->isPositive()) {
            $errors['totalAmount'] = 'TotalAmount must be greater than zero.';
        }

        if (trim($this->orderIdentifier) === '') {
            $errors['orderIdentifier'] = 'OrderIdentifier must not be empty.';
        }

        if ($errors !== []) {
            throw new ValidationException('Refund request validation failed.', $errors);
        }
    }

    /**
     * Resolve the ISO 4217 numeric currency string from the Money object.
     */
    private function currencyNumericString(): string
    {
        $alpha = $this->totalAmount->getCurrency()->getCurrencyCode();

        try {
            return CurrencyCode::fromAlphaCode($alpha)->numericString();
        } catch (\ValueError) {
            return $alpha;
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TransactionIdentifier' => $this->transactionIdentifier,
            'TotalAmount'           => (float) (string) $this->totalAmount->getAmount(),
            'CurrencyCode'          => $this->currencyNumericString(),
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
