<?php

declare(strict_types=1);

namespace PowerTranz\Exception;

/**
 * Thrown when authentication fails (HTTP 401/403 or invalid credentials).
 */
class AuthenticationException extends ApiException
{
}
