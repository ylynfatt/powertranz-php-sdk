<?php

declare(strict_types=1);

namespace PowerTranz\Config;

/**
 * PowerTranz API environment selection.
 *
 * Each case value is the base URL for that environment.
 */
enum Environment: string
{
    case SANDBOX    = 'https://staging.ptranz.com/api/';
    case PRODUCTION = 'https://api.ptranz.com/api/';

    public function baseUrl(): string
    {
        return $this->value;
    }

    public function isSandbox(): bool
    {
        return $this === self::SANDBOX;
    }
}
