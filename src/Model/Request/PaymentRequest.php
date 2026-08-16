<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Completes a transaction after a 3DS challenge.
 *
 * Send this request with the SpiToken received in a {@see \PowerTranz\Model\Response\ThreeDSecureChallenge}
 * response once the cardholder has completed the 3DS challenge.
 *
 * SpiTokens are valid for 5 minutes. Use {@see \PowerTranz\Exception\TokenExpiredException}
 * to detect expiry errors returned by the gateway.
 *
 * ## Wire format
 *
 * This endpoint is unlike every other one in the API: the request body is the
 * bare token as a JSON string, not an object wrapping it.
 *
 *     "SPI_TOKEN"
 *
 * Hence {@see jsonSerialize()} returns a string rather than an array, and
 * {@code json_encode()} produces the quoted scalar the gateway expects.
 *
 * Corresponds to POST /spi/payment.
 *
 * @see https://developer.powertranz.com/docs/spi-3ds-1
 */
final class PaymentRequest implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'SpiToken must not be empty.')]
        public readonly string $spiToken,
    ) {
        RequestValidator::validate($this, 'SpiToken is required to complete a 3DS payment.');
    }

    /**
     * The bare token — encoded as a quoted JSON string, not an object.
     */
    public function jsonSerialize(): string
    {
        return $this->spiToken;
    }
}
