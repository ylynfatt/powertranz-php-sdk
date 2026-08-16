<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Card payment source — use when charging a raw card number.
 *
 * For repeat/recurring charges use {@see TokenSource} with the PanToken
 * returned from a previous transaction.
 *
 * All fields are validated on construction via Symfony Validator constraints
 * declared as PHP 8 attributes.  A {@see \PowerTranz\Exception\ValidationException}
 * is thrown immediately if any constraint is violated — before any HTTP call is made.
 *
 * The digit patterns anchor with {@code \A} and {@code \z}, never {@code ^} and
 * {@code $}: in PCRE, {@code $} also matches immediately before a trailing
 * newline, so {@code /^\d{3,4}$/} accepts {@code "123\n"}. Card fields arrive
 * straight from posted form data, where a stray newline is ordinary. Letting one
 * through would send a malformed PAN to the gateway and, worse, feed a value one
 * byte longer than it looks to {@see maskedPan()} and the SDK's log redaction,
 * both of which slice the PAN by offset.
 */
final class CardSource implements JsonSerializable
{
    public function __construct(
        #[Assert\Regex(
            pattern: '/\A\d{12,19}\z/',
            message: 'CardPan must be 12–19 digits.',
        )]
        public readonly string $cardPan,

        #[Assert\Regex(
            pattern: '/\A\d{4}\z/',
            message: 'CardExpiration must be in YYMM format (e.g. 2512 for December 2025).',
        )]
        public readonly string $cardExpiration,

        #[Assert\Regex(
            pattern: '/\A\d{3,4}\z/',
            message: 'CardCvv must be 3 or 4 digits.',
        )]
        public readonly string $cardCvv,

        #[Assert\NotBlank(message: 'CardholderName must not be empty.')]
        #[Assert\Length(
            min: 2,
            max: 45,
            minMessage: 'CardholderName must be between 2 and 45 characters.',
            maxMessage: 'CardholderName must be between 2 and 45 characters.',
        )]
        public readonly string $cardholderName,
    ) {
        RequestValidator::validate($this, 'Card source validation failed.');
    }

    /**
     * Returns a masked PAN showing the BIN (first 6) and last 4 digits.
     * Example: 4111111111111111 → 411111******1111
     */
    public function maskedPan(): string
    {
        $len = strlen($this->cardPan);

        if ($len <= 10) {
            return str_repeat('*', $len);
        }

        return substr($this->cardPan, 0, 6)
            . str_repeat('*', $len - 10)
            . substr($this->cardPan, -4);
    }

    public function jsonSerialize(): mixed
    {
        return [
            'CardPan'        => $this->cardPan,
            'CardExpiration' => $this->cardExpiration,
            'CardCvv'        => $this->cardCvv,
            'CardholderName' => $this->cardholderName,
        ];
    }
}
