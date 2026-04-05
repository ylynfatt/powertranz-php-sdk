<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
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
 * The {@see $totalAmount} is expressed as a {@see Money} object so that the
 * currency and amount are kept together and arbitrary-precision decimal
 * arithmetic is used throughout — no float rounding surprises.
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
        public readonly Money $totalAmount,
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

        if (!$this->totalAmount->isPositive()) {
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

    /**
     * Resolve the ISO 4217 numeric currency string from the Money object.
     * Falls back to the alpha code string directly if not in our enum.
     */
    private function currencyNumericString(): string
    {
        $alpha = $this->totalAmount->getCurrency()->getCurrencyCode();

        try {
            return CurrencyCode::fromAlphaCode($alpha)->numericString();
        } catch (\ValueError) {
            // Currency is valid for brick/money but not in our enum — pass alpha as-is
            // (should not happen in production; the caller would typically use CurrencyCode::USD->money())
            return $alpha;
        }
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'TotalAmount'           => (float) (string) $this->totalAmount->getAmount(),
            'CurrencyCode'          => $this->currencyNumericString(),
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
