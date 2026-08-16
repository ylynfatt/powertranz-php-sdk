<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The {@code ExtendedData} object carried by SPI transaction requests.
 *
 * This is where the 3DS parameters and the merchant callback URL live — not at
 * the top level of the request, and not inside the top-level {@code ThreeDSecure}
 * flag (which is a plain boolean).
 *
 * {@see $merchantResponseUrl} is required by the gateway for 3DS or any flow
 * involving a redirect. PowerTranz POSTs the authentication result to it once the
 * cardholder finishes in the iframe, which is how control returns to your
 * application — without it a 3DS transaction cannot complete.
 *
 * @see https://developer.powertranz.com/reference/post_spi-sale
 */
final class ExtendedData implements JsonSerializable
{
    public function __construct(
        /**
         * Where PowerTranz sends the cardholder (and POSTs the result) when the
         * transaction completes. Required for 3DS and hosted-page flows.
         */
        #[Assert\NotBlank(normalizer: 'trim', message: 'MerchantResponseUrl must not be empty.')]
        #[Assert\Url(message: 'MerchantResponseUrl must be a valid URL.')]
        public readonly string $merchantResponseUrl,

        #[Assert\Valid]
        public readonly ?ThreeDSecure $threeDSecure = null,

        #[Assert\Valid]
        public readonly ?HostedPage $hostedPage = null,
    ) {
        RequestValidator::validate($this, 'ExtendedData validation failed.');
    }

    /**
     * Convenience factory for a standard 3DS transaction: a callback URL plus
     * default 3DS parameters.
     */
    public static function forThreeDSecure(
        string $merchantResponseUrl,
        ?ThreeDSecure $threeDSecure = null,
    ): self {
        return new self(
            merchantResponseUrl: $merchantResponseUrl,
            threeDSecure:        $threeDSecure ?? new ThreeDSecure(),
        );
    }

    /**
     * Convenience factory for a hosted payment page, with 3DS parameters
     * included since HPP transactions are normally 3DS-enabled.
     */
    public static function forHostedPage(
        string $merchantResponseUrl,
        HostedPage $hostedPage,
        ?ThreeDSecure $threeDSecure = null,
    ): self {
        return new self(
            merchantResponseUrl: $merchantResponseUrl,
            threeDSecure:        $threeDSecure ?? new ThreeDSecure(),
            hostedPage:          $hostedPage,
        );
    }

    public function jsonSerialize(): mixed
    {
        $data = [];

        if ($this->threeDSecure !== null) {
            $data['ThreeDSecure'] = $this->threeDSecure->jsonSerialize();
        }

        if ($this->hostedPage !== null) {
            $data['HostedPage'] = $this->hostedPage->jsonSerialize();
        }

        $data['MerchantResponseUrl'] = $this->merchantResponseUrl;

        return $data;
    }
}
