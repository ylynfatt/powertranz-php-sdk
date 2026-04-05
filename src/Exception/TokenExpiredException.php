<?php

declare(strict_types=1);

namespace PowerTranz\Exception;

/**
 * Thrown when a SpiToken is used after its 5-minute validity window has expired.
 */
class TokenExpiredException extends PowerTranzException
{
    public function __construct(string $message = 'The SpiToken has expired. SpiTokens are valid for 5 minutes after issuance.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
