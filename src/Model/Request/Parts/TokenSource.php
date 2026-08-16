<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Token-based payment source — charge a stored card without re-entering card
 * details, using the token from a previous tokenised transaction.
 *
 * ## The field names differ between response and request
 *
 * The gateway *returns* the token as {@code PanToken} on the response, but
 * *expects* it back as {@code Source.Token}. Passing it as {@code PanToken} in
 * the request means the gateway sees no card data at all — so read
 * {@see \PowerTranz\Model\Response\SpiResponse::$panToken} and hand that value to
 * this class, which puts it in the right place.
 *
 * {@see $tokenType} is only needed for First Atlantic Commerce tokens, which use
 * {@see self::TYPE_FAC}. Leave it null for tokens issued by PowerTranz itself.
 *
 * The optional {@see $cardCvv} is validated only when provided; null is always
 * valid (Symfony's Regex constraint skips null values by design).
 *
 * @see https://developer.powertranz.com/reference/post_spi-sale
 */
final class TokenSource implements JsonSerializable
{
    /** Token type for First Atlantic Commerce (FAC) tokens. */
    public const TYPE_FAC = 'PG2';

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'Token is required.')]
        public readonly string $token,

        // Regex skips null — null means "no CVV supplied", which is valid for token payments.
        // Anchored with \z rather than $, which would also match before a trailing
        // newline and so accept "123\n" straight out of posted form data.
        #[Assert\Regex(
            pattern: '/\A\d{3,4}\z/',
            message: 'CardCvv must be 3 or 4 digits.',
        )]
        public readonly ?string $cardCvv = null,

        #[Assert\Choice(
            choices: [self::TYPE_FAC],
            message: 'TokenType must be PG2, which is only used for FAC tokens.',
        )]
        public readonly ?string $tokenType = null,
    ) {
        RequestValidator::validate($this, 'Token source validation failed.');
    }

    /**
     * Build a source from a First Atlantic Commerce token, tagging it with the
     * PG2 token type the gateway requires for them.
     */
    public static function fac(string $token, ?string $cardCvv = null): self
    {
        return new self(
            token:     $token,
            cardCvv:   $cardCvv,
            tokenType: self::TYPE_FAC,
        );
    }

    public function jsonSerialize(): mixed
    {
        $data = ['Token' => $this->token];

        if ($this->tokenType !== null) {
            $data['TokenType'] = $this->tokenType;
        }

        if ($this->cardCvv !== null) {
            $data['CardCvv'] = $this->cardCvv;
        }

        return $data;
    }
}
