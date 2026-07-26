<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use JsonSerializable;
use PowerTranz\Validator\Constraint\PositiveMoney;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Capture previously authorised funds.
 *
 * The captured amount may be less than or equal to the authorised amount.
 * Partial captures are supported.
 *
 * All monetary values are expressed as {@see Money} objects.  When tip or tax
 * amounts are supplied they must share the same currency as {@see $totalAmount};
 * this cross-field rule is enforced by the {@see validateCurrencyConsistency()}
 * callback constraint.
 *
 * Corresponds to POST /capture.
 */
final class CaptureRequest implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'TransactionIdentifier must not be empty.')]
        public readonly string $transactionIdentifier,

        #[PositiveMoney(message: 'TotalAmount must be greater than zero.')]
        public readonly Money $totalAmount,

        public readonly ?Money $tipAmount = null,
        public readonly ?Money $taxAmount = null,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
    ) {
        RequestValidator::validate($this, 'Capture request validation failed.');
    }

    /**
     * Cross-field constraint: tip and tax amounts must share the same currency
     * as the capture total.
     *
     * Symfony invokes this automatically when validating a {@see CaptureRequest}
     * instance because of the {@see Assert\Callback} attribute.
     */
    #[Assert\Callback]
    public function validateCurrencyConsistency(ExecutionContextInterface $context): void
    {
        $currency = $this->totalAmount->getCurrency();

        if ($this->tipAmount !== null && !$this->tipAmount->getCurrency()->is($currency)) {
            $context
                ->buildViolation('TipAmount currency must match TotalAmount currency.')
                ->atPath('tipAmount')
                ->addViolation();
        }

        if ($this->taxAmount !== null && !$this->taxAmount->getCurrency()->is($currency)) {
            $context
                ->buildViolation('TaxAmount currency must match TotalAmount currency.')
                ->atPath('taxAmount')
                ->addViolation();
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
