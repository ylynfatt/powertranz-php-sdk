<?php

declare(strict_types=1);

namespace PowerTranz\Enum;

/**
 * ISO 8583 response codes returned by the PowerTranz gateway.
 *
 * The two most important codes for branching logic are:
 *   - APPROVED (00): Transaction succeeded.
 *   - THREE_DS_REDIRECT (3D0): Cardholder must complete a 3DS challenge before
 *     the payment can be finalised via /spi/payment.
 */
enum IsoResponseCode: string
{
    case APPROVED              = '00';
    case REFER_TO_ISSUER       = '01';
    case INVALID_MERCHANT      = '03';
    case DO_NOT_HONOUR         = '05';
    case GENERAL_ERROR         = '06';
    case HONOR_WITH_ID         = '08';
    case INVALID_TRANSACTION   = '12';
    case INVALID_AMOUNT        = '13';
    case INVALID_CARD_NUMBER   = '14';
    case NO_SUCH_ISSUER        = '15';
    case INSUFFICIENT_FUNDS    = '51';
    case EXPIRED_CARD          = '54';
    case INCORRECT_PIN         = '55';
    case TRANSACTION_NOT_PERMITTED = '57';
    case EXCEEDS_LIMIT         = '61';
    case RESTRICTED_CARD       = '62';
    case SECURITY_VIOLATION    = '63';
    case INVALID_CVV           = '82';
    case THREE_DS_REDIRECT     = '3D0';
    case THREE_DS_COMPLETE     = '3D1';

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function requires3dsChallenge(): bool
    {
        return $this === self::THREE_DS_REDIRECT;
    }

    public function isDeclined(): bool
    {
        return !$this->isApproved() && !$this->requires3dsChallenge();
    }

    public function label(): string
    {
        return match ($this) {
            self::APPROVED              => 'Approved',
            self::REFER_TO_ISSUER       => 'Refer to card issuer',
            self::INVALID_MERCHANT      => 'Invalid merchant',
            self::DO_NOT_HONOUR         => 'Do not honour',
            self::GENERAL_ERROR         => 'General error',
            self::HONOR_WITH_ID         => 'Honour with identification',
            self::INVALID_TRANSACTION   => 'Invalid transaction',
            self::INVALID_AMOUNT        => 'Invalid amount',
            self::INVALID_CARD_NUMBER   => 'Invalid card number',
            self::NO_SUCH_ISSUER        => 'No such issuer',
            self::INSUFFICIENT_FUNDS    => 'Insufficient funds',
            self::EXPIRED_CARD          => 'Expired card',
            self::INCORRECT_PIN         => 'Incorrect PIN',
            self::TRANSACTION_NOT_PERMITTED => 'Transaction not permitted',
            self::EXCEEDS_LIMIT         => 'Exceeds withdrawal limit',
            self::RESTRICTED_CARD       => 'Restricted card',
            self::SECURITY_VIOLATION    => 'Security violation',
            self::INVALID_CVV           => 'Invalid CVV',
            self::THREE_DS_REDIRECT     => '3DS challenge required',
            self::THREE_DS_COMPLETE     => '3DS authentication complete',
        };
    }
}
