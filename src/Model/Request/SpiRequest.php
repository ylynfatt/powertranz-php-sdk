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
use PowerTranz\Validator\RequestValidator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Abstract base for all SPI transaction requests (Auth, Sale, RiskMgmt).
 *
 * The {@see $totalAmount} is expressed as a {@see Money} object so that the
 * currency and amount are kept together and arbitrary-precision decimal
 * arithmetic is used throughout — no float rounding surprises.
 *
 * The {@see $transactionIdentifier} is optional — a UUID v4 is automatically
 * generated when not supplied.  If a value is provided it must be a valid
 * RFC 4122 UUID so that the PowerTranz gateway can uniquely identify and
 * de-duplicate requests; this is enforced by the {@see Assert\Uuid} constraint.
 *
 * All property constraints are declared as PHP 8 attributes and evaluated via
 * {@see RequestValidator::validate()}.  Because Symfony's metadata factory
 * traverses parent class properties, subclasses (AuthRequest, SaleRequest, …)
 * inherit these constraints at no extra cost.
 */
abstract class SpiRequest implements JsonSerializable
{
    /**
     * Unique identifier for this transaction.
     *
     * Auto-generated as a UUID v4 when not explicitly provided.
     * When supplied by the caller it must be a valid RFC 4122 UUID.
     */
    #[Assert\Uuid(message: 'TransactionIdentifier must be a valid UUID (e.g. 550e8400-e29b-41d4-a716-446655440000).')]
    public readonly string $transactionIdentifier;

    public function __construct(
        public readonly Money $totalAmount,

        #[Assert\NotBlank(normalizer: 'trim', message: 'OrderIdentifier must not be empty.')]
        #[Assert\Length(
            max: 255,
            maxMessage: 'OrderIdentifier must not exceed 255 characters.',
        )]
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

        if (!$this->totalAmount->isPositive()) {
            throw new ValidationException(
                'SPI request validation failed.',
                ['totalAmount' => 'TotalAmount must be greater than zero.'],
            );
        }

        RequestValidator::validate($this, 'SPI request validation failed.');
    }

    /**
     * Return an existing UUID unchanged, or generate a new UUID v4 when null/blank.
     *
     * Validation of the value (i.e. confirming it is a valid UUID) is intentionally
     * deferred to {@see RequestValidator::validate()} via the {@see Assert\Uuid}
     * attribute on the property, so that invalid caller-supplied identifiers are
     * reported in the same {@see \PowerTranz\Exception\ValidationException} as any
     * other field errors rather than failing independently.
     */
    private function resolveTransactionIdentifier(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return Uuid::uuid4()->toString();
        }

        return $value;
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
            // Currency is valid for brick/money but not in our enum.
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
