<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Exception\ValidationException;

/**
 * Void (cancel) an authorised or captured transaction before settlement.
 *
 * Corresponds to POST /void.
 */
final class VoidRequest implements JsonSerializable
{
    public function __construct(
        public readonly string $transactionIdentifier,
        public readonly ?string $externalIdentifier = null,
        public readonly ?string $externalGroupIdentifier = null,
    ) {
        if (trim($this->transactionIdentifier) === '') {
            throw new ValidationException(
                'Void request validation failed.',
                ['transactionIdentifier' => 'TransactionIdentifier must not be empty.']
            );
        }
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
