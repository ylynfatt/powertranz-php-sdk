<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\Address;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\Parts\TokenSource;

/**
 * Abstract base for all SPI transaction requests (Auth, Sale, RiskMgmt).
 */
abstract class SpiRequest implements JsonSerializable
{
    public function __construct(
        public readonly float $totalAmount,
        public readonly CurrencyCode $currencyCode,
        public readonly string $orderIdentifier,
        public readonly string $transactionIdentifier,
        public readonly CardSource|TokenSource $source,
        public readonly bool $tokenize = false,
        public readonly ?ThreeDSecure $threeDSecure = null,
        public readonly ?Address $billingAddress = null,
        public readonly ?Address $shippingAddress = null,
        public readonly ?string $extendedData = null,
    ) {
        $this->validateBase();
    }

    private function validateBase(): void
    {
        $errors = [];

        if ($this->totalAmount <= 0) {
            $errors['totalAmount'] = 'TotalAmount must be greater than zero.';
        }

        if (trim($this->orderIdentifier) === '') {
            $errors['orderIdentifier'] = 'OrderIdentifier must not be empty.';
        }

        if (strlen($this->orderIdentifier) > 255) {
            $errors['orderIdentifier'] = 'OrderIdentifier must not exceed 255 characters.';
        }

        if (trim($this->transactionIdentifier) === '') {
            $errors['transactionIdentifier'] = 'TransactionIdentifier must not be empty.';
        }

        if ($errors !== []) {
            throw new ValidationException('SPI request validation failed.', $errors);
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TotalAmount'           => round($this->totalAmount, 2),
            'CurrencyCode'          => $this->currencyCode->numericString(),
            'OrderIdentifier'       => $this->orderIdentifier,
            'TransactionIdentifier' => $this->transactionIdentifier,
            'Source'                => $this->source->jsonSerialize(),
            'Tokenize'              => $this->tokenize,
        ];

        if ($this->threeDSecure !== null) {
            $data['ThreeDSecure'] = $this->threeDSecure->jsonSerialize();
        }

        if ($this->billingAddress !== null) {
            $data['BillingAddress'] = $this->billingAddress->jsonSerialize();
        }

        if ($this->shippingAddress !== null) {
            $data['ShippingAddress'] = $this->shippingAddress->jsonSerialize();
        }

        if ($this->extendedData !== null) {
            $data['ExtendedData'] = $this->extendedData;
        }

        return $data;
    }
}
