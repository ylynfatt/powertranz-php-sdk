<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;

/**
 * 3DS configuration embedded in an SPI authorise or sale request.
 *
 * When enabled, the gateway will attempt 3DS authentication. If the issuer
 * requires a cardholder challenge, the response IsoResponseCode will be '3D0'
 * and the response will contain a {@see \PowerTranz\Model\Response\ThreeDSecureChallenge}.
 */
final class ThreeDSecure implements JsonSerializable
{
    /**
     * Challenge window sizes supported by 3DS 2.x.
     * Value is sent as-is in the ChallengeWindowSize field.
     */
    public const WINDOW_250x400  = '01';
    public const WINDOW_390x400  = '02';
    public const WINDOW_500x600  = '03';
    public const WINDOW_600x400  = '04';
    public const WINDOW_FULLPAGE = '05';

    public function __construct(
        public readonly bool $enabled = true,
        public readonly ?BrowserDetails $browserDetails = null,
        public readonly string $challengeWindowSize = self::WINDOW_500x600,
        public readonly ?string $merchantUrl = null,
    ) {
    }

    /**
     * Convenience factory: create a disabled 3DS object (for non-3DS transactions).
     */
    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    /**
     * Convenience factory: 3DS enabled with browser details.
     */
    public static function withBrowser(BrowserDetails $browserDetails, string $challengeWindowSize = self::WINDOW_500x600): self
    {
        return new self(
            enabled:             true,
            browserDetails:      $browserDetails,
            challengeWindowSize: $challengeWindowSize,
        );
    }

    public function jsonSerialize(): mixed
    {
        $data = [
            'Enabled'             => $this->enabled,
            'ChallengeWindowSize' => $this->challengeWindowSize,
        ];

        if ($this->browserDetails !== null) {
            $data['BrowserDetails'] = $this->browserDetails->jsonSerialize();
        }

        if ($this->merchantUrl !== null) {
            $data['MerchantUrl'] = $this->merchantUrl;
        }

        return $data;
    }
}
