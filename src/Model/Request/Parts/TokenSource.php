<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Token-based payment source — use the PanToken returned from a previous
 * transaction to charge a stored card without re-entering card details.
 *
 * The optional {@see $cardCvv} is validated only when provided; null is always
 * valid (Symfony's Regex constraint skips null values by design).
 */
final class TokenSource implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'PanToken is required.')]
        public readonly string $panToken,

        // Regex skips null — null means "no CVV supplied", which is valid for token payments.
        #[Assert\Regex(
            pattern: '/^\d{3,4}$/',
            message: 'CardCvv must be 3 or 4 digits.',
        )]
        public readonly ?string $cardCvv = null,
    ) {
        RequestValidator::validate($this, 'Token source validation failed.');
    }

    public function jsonSerialize(): mixed
    {
        $data = ['PanToken' => $this->panToken];

        if ($this->cardCvv !== null) {
            $data['CardCvv'] = $this->cardCvv;
        }

        return $data;
    }
}
