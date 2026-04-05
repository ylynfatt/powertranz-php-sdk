<?php

declare(strict_types=1);

namespace PowerTranz\Enum;

/**
 * Transaction type codes returned in gateway responses.
 */
enum TransactionType: int
{
    case AUTH    = 1;
    case SALE    = 2;
    case CAPTURE = 3;
    case REFUND  = 4;
    case VOID    = 5;
    case CREDIT  = 6;

    public function label(): string
    {
        return match ($this) {
            self::AUTH    => 'Authorisation',
            self::SALE    => 'Sale',
            self::CAPTURE => 'Capture',
            self::REFUND  => 'Refund',
            self::VOID    => 'Void',
            self::CREDIT  => 'Credit',
        };
    }
}
