<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use JsonSerializable;
use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Validator\Constraint\PositiveMoney;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;
use PowerTranz\Model\Request\Parts\Address;

/**
 * Refund a previously captured transaction, in full or partially.
 *
 * The {@see $totalAmount} is expressed as a {@see Money} object — the currency
 * is derived from it automatically, so no separate currency parameter is needed.
 *
 * Corresponds to POST /refund.
 */
final class RefundRequest implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'TransactionIdentifier must not be empty.')]
        public readonly string $transactionIdentifier,

        #[PositiveMoney(message: 'TotalAmount must be greater than zero.')]
        public readonly Money $totalAmount,

        #[Assert\NotBlank(normalizer: 'trim', message: 'OrderIdentifier must not be empty.')]
        public readonly string $orderIdentifier,

        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
        public readonly ?Address $billingAddress = null,
    ) {
        RequestValidator::validate($this, 'Refund request validation failed.');
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
