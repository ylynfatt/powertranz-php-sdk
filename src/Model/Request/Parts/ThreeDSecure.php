<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * 3DS2 parameters, sent as {@code ExtendedData.ThreeDSecure}.
 *
 * Note that this is *not* the top-level {@code ThreeDSecure} field — that one is
 * a plain boolean flag switching 3DS on for the transaction. This object carries
 * the parameters, and the gateway requires it whenever that flag is true.
 *
 * @see https://developer.powertranz.com/reference/post_spi-sale
 */
final class ThreeDSecure implements JsonSerializable
{
    /**
     * Challenge window sizes. Sent as an integer, not a zero-padded string.
     */
    public const WINDOW_250x400  = 1;
    public const WINDOW_390x400  = 2;
    public const WINDOW_500x600  = 3;
    public const WINDOW_600x400  = 4;
    public const WINDOW_FULLPAGE = 5;

    /** Requestor challenge indicators. */
    public const CHALLENGE_NO_PREFERENCE = '01';
    public const CHALLENGE_NONE          = '02';
    public const CHALLENGE_PREFERRED     = '03';
    public const CHALLENGE_MANDATED      = '04';

    /** Authentication indicators (threeDSRequestorAuthenticationInd). */
    public const AUTH_PAYMENT       = '01';
    public const AUTH_ADD_CARD      = '04';
    public const AUTH_MAINTAIN_CARD = '05';

    /** Message categories. */
    public const CATEGORY_PA  = '01';
    public const CATEGORY_NPA = '02';

    public function __construct(
        #[Assert\Choice(
            choices: [1, 2, 3, 4, 5],
            message: 'ChallengeWindowSize must be 1 (250x400), 2 (390x400), 3 (500x600), 4 (600x400) or 5 (full page).',
        )]
        public readonly int $challengeWindowSize = self::WINDOW_FULLPAGE,

        #[Assert\Choice(
            choices: ['01', '02', '03', '04'],
            message: 'ChallengeIndicator must be 01, 02, 03 or 04.',
        )]
        public readonly string $challengeIndicator = self::CHALLENGE_NO_PREFERENCE,

        #[Assert\Choice(
            choices: ['01', '04', '05'],
            message: 'AuthenticationIndicator must be 01, 04 or 05.',
        )]
        public readonly ?string $authenticationIndicator = null,

        #[Assert\Choice(
            choices: ['01', '02'],
            message: 'MessageCategory must be 01 or 02.',
        )]
        public readonly ?string $messageCategory = null,
    ) {
        RequestValidator::validate($this, '3DS parameter validation failed.');
    }

    /**
     * Convenience factory: request that the issuer not challenge the cardholder.
     *
     * The issuer may still mandate one — this expresses a preference only.
     */
    public static function withoutChallenge(int $challengeWindowSize = self::WINDOW_FULLPAGE): self
    {
        return new self(
            challengeWindowSize: $challengeWindowSize,
            challengeIndicator:  self::CHALLENGE_NONE,
        );
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'ChallengeWindowSize' => $this->challengeWindowSize,
            'ChallengeIndicator'  => $this->challengeIndicator,
        ];

        if ($this->authenticationIndicator !== null) {
            $data['AuthenticationIndicator'] = $this->authenticationIndicator;
        }

        if ($this->messageCategory !== null) {
            $data['MessageCategory'] = $this->messageCategory;
        }

        return $data;
    }
}
