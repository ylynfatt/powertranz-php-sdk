<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Void (cancel) an authorised or captured transaction before settlement.
 *
 * Corresponds to POST /void.
 */
final class VoidRequest implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'TransactionIdentifier must not be empty.')]
        public readonly string $transactionIdentifier,

        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
    ) {
        RequestValidator::validate($this, 'Void request validation failed.');
    }

    public function jsonSerialize(): mixed
    {
        $data = ['TransactionIdentifier' => $this->transactionIdentifier];

        if ($this->externalIdentifier !== null) {
            $data['ExternalIdentifier'] = $this->externalIdentifier;
        }

        if ($this->externalGroupIdentifier !== null) {
            $data['ExternalGroupIdentifier'] = $this->externalGroupIdentifier;
        }

        return $data;
    }
}
