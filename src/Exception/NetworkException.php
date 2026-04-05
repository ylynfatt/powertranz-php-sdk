<?php

declare(strict_types=1);

namespace PowerTranz\Exception;

/**
 * Thrown when a transport-level failure occurs (connection timeout, DNS failure, etc.).
 */
class NetworkException extends PowerTranzException
{
}
