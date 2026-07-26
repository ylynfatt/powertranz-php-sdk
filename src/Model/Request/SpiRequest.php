<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use JsonSerializable;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Model\Request\Parts\Address;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ExtendedData;
use PowerTranz\Model\Request\Parts\TokenSource;
use PowerTranz\Validator\Constraint\PositiveMoney;
use PowerTranz\Validator\RequestValidator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
        #[PositiveMoney(message: 'TotalAmount must be greater than zero.')]
        public readonly Money $totalAmount,

        #[Assert\NotBlank(normalizer: 'trim', message: 'OrderIdentifier must not be empty.')]
        #[Assert\Length(
            max: 255,
            maxMessage: 'OrderIdentifier must not exceed 255 characters.',
        )]
        public readonly string $orderIdentifier,

        /**
         * Card or token data. Omitted for hosted-page transactions, where the
         * cardholder types their card into the gateway-hosted iframe instead —
         * see {@see ExtendedData::$hostedPage}.
         */
        #[Assert\Valid]
        public readonly CardSource|TokenSource|null $source = null,

        ?string $transactionIdentifier = null,

        /**
         * Top-level {@code ThreeDSecure} flag — a plain boolean switching 3DS on
         * for this transaction. The parameters live in
         * {@see ExtendedData::$threeDSecure}.
         *
         * Defaults to false so a non-3DS transaction needs nothing further; set
         * it true and supply {@see $extendedData} to authenticate.
         */
        public readonly bool $threeDSecure = false,

        #[Assert\Valid]
        public readonly ?ExtendedData $extendedData = null,

        #[Assert\Valid]
        public readonly ?Address $billingAddress = null,
        #[Assert\Valid]
        public readonly ?Address $shippingAddress = null,

        public readonly ?bool $addressMatch = null,
    ) {
        $this->transactionIdentifier = $this->resolveTransactionIdentifier($transactionIdentifier);

        RequestValidator::validate($this, 'SPI request validation failed.');
    }

    /**
     * Cross-field constraint: enabling 3DS obliges the caller to supply the
     * parameters the gateway needs to run it.
     *
     * Without ExtendedData there is no MerchantResponseUrl, and without that
     * PowerTranz has nowhere to post the authentication result — the cardholder
     * completes the challenge in the iframe and control never returns. The
     * gateway rejects such requests, so catching it locally turns an opaque
     * remote failure into a named field error.
     */
    /**
     * Cross-field constraint: the gateway needs card data from exactly one place.
     *
     * Either the request carries a Source, or it carries hosted-page parameters
     * and the cardholder enters the card in the gateway's iframe. Sending both is
     * contradictory, and sending neither leaves nothing to charge.
     */
    #[Assert\Callback]
    public function validateSourceOrHostedPage(ExecutionContextInterface $context): void
    {
        $hasHostedPage = $this->extendedData?->hostedPage !== null;

        if ($this->source === null && !$hasHostedPage) {
            $context
                ->buildViolation('Either a Source or ExtendedData.HostedPage is required.')
                ->atPath('source')
                ->addViolation();
        }

        if ($this->source !== null && $hasHostedPage) {
            $context
                ->buildViolation('A Source must not be sent with ExtendedData.HostedPage — the cardholder enters card data on the hosted page.')
                ->atPath('source')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateThreeDSecureParameters(ExecutionContextInterface $context): void
    {
        if (!$this->threeDSecure) {
            return;
        }

        if ($this->extendedData === null) {
            $context
                ->buildViolation('ExtendedData with a MerchantResponseUrl is required when ThreeDSecure is enabled.')
                ->atPath('extendedData')
                ->addViolation();

            return;
        }

        if ($this->extendedData->threeDSecure === null) {
            $context
                ->buildViolation('ExtendedData.ThreeDSecure parameters are required when ThreeDSecure is enabled.')
                ->atPath('extendedData.threeDSecure')
                ->addViolation();
        }
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
            'TransactionIdentifier' => $this->transactionIdentifier,
            'TotalAmount'           => (float) (string) $this->totalAmount->getAmount(),
            'CurrencyCode'          => $this->currencyNumericString(),
            'ThreeDSecure'          => $this->threeDSecure,
            'OrderIdentifier'       => $this->orderIdentifier,
        ];

        if ($this->source !== null) {
            $data['Source'] = $this->source->jsonSerialize();
        }

        if ($this->billingAddress !== null) {
            $data['BillingAddress'] = $this->billingAddress->jsonSerialize();
        }

        if ($this->shippingAddress !== null) {
            $data['ShippingAddress'] = $this->shippingAddress->jsonSerialize();
        }

        if ($this->addressMatch !== null) {
            $data['AddressMatch'] = $this->addressMatch;
        }

        if ($this->extendedData !== null) {
            $data['ExtendedData'] = $this->extendedData->jsonSerialize();
        }

        return $data;
    }
}
