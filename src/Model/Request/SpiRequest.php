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
use Ramsey\Uuid\Uuid;

/**
 * Abstract base for all SPI transaction requests (Auth, Sale, RiskMgmt).
 *
 * The {@see $transactionIdentifier} is optional — a UUID v4 is automatically
 * generated when not supplied. If you pass your own value it must be a valid
 * UUID (RFC 4122) so that the PowerTranz gateway can uniquely identify and
 * de-duplicate requests.
 */
abstract class SpiRequest implements JsonSerializable
{
    /**
     * Unique identifier for this transaction.
     * Auto-generated as a UUID v4 when not explicitly provided.
     */
    public readonly string $transactionIdentifier;

    public function __construct(
        public readonly float $totalAmount,
        public readonly CurrencyCode $currencyCode,
        public readonly string $orderIdentifier,
        public readonly CardSource|TokenSource $source,
        ?string $transactionIdentifier = null,
        public readonly bool $tokenize = false,
        public readonly ?ThreeDSecure $threeDSecure = null,
        public readonly ?Address $billingAddress = null,
        public readonly ?Address $shippingAddress = null,
        public readonly ?string $extendedData = null,
    ) {
        $this->transactionIdentifier = $this->resolveTransactionIdentifier($transactionIdentifier);
        $this->validateBase();
    }

    /**
     * Auto-generate a UUID v4 if no identifier was supplied.
     * Validate the format if one was supplied.
     */
    private function resolveTransactionIdentifier(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return Uuid::uuid4()->toString();
        }

        if (!Uuid::isValid($value)) {
            throw new ValidationException(
                'SPI request validation failed.',
                ['transactionIdentifier' => 'TransactionIdentifier must be a valid UUID (e.g. 550e8400-e29b-41d4-a716-446655440000).'],
            );
        }

        return $value;
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
