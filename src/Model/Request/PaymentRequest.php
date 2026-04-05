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
 * Corresponds to POST /spi/payment.
 */
final class PaymentRequest implements JsonSerializable
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'SpiToken must not be empty.')]
        public readonly string $spiToken,
    ) {
        RequestValidator::validate($this, 'SpiToken is required to complete a 3DS payment.');
    }

    public function jsonSerialize(): mixed
    {
        return ['SpiToken' => $this->spiToken];
    }
}
