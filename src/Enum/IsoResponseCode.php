<?php

declare(strict_types=1);

namespace PowerTranz\Enum;

/**
 * Response codes returned by the PowerTranz gateway in {@code IsoResponseCode}.
 *
 * Two distinct families share this field, and which one you get depends on the
 * stage of the transaction:
 *
 *  - **Gateway status codes** (SP4, HP0, TK0, FC0, 3D0, 3D1, 3D4–3D6) report the
 *    outcome of a non-financial step: SPI preprocessing, 3DS authentication,
 *    tokenisation or fraud check.
 *  - **ISO 8583 codes** (00, 05, 51, …) are the issuing bank's answer to a
 *    financial request, returned from the payment completion endpoints.
 *
 * ## The SPI flow, and why SP4 matters
 *
 * The first call (/spi/auth, /spi/sale, /spi/riskmgmt) does not return 3D0. It
 * returns **SP4** — "SPI Preprocessing complete" — together with RedirectData
 * and an SpiToken. That is the code which tells you a redirect is pending and
 * the RedirectData must be rendered in an iframe.
 *
 * **3D0 arrives later, and elsewhere.** Once the cardholder finishes (or skips)
 * the challenge, PowerTranz POSTs the authentication result to your
 * MerchantResponseUrl, and *that* payload carries 3D0. It is not seen on the
 * response to the initial request.
 *
 * @see https://developer.powertranz.com/docs/isoresponsecode
 */
enum IsoResponseCode: string
{
    // ---------------------------------------------------------------------
    // Gateway status codes (non-financial steps)
    // ---------------------------------------------------------------------

    /** SPI preprocessing complete. Returned with RedirectData + SpiToken. */
    case SPI_PREPROCESSING_COMPLETE = 'SP4';

    /** Hosted Payment Page preprocessing complete. */
    case HPP_PREPROCESSING_COMPLETE = 'HP0';

    /** 3DS authentication completed without errors. Posted to MerchantResponseUrl. */
    case THREE_DS_COMPLETE = '3D0';

    /** 3DS2 not supported for this card; may proceed as standard e-commerce. */
    case THREE_DS_NOT_SUPPORTED = '3D1';

    /** Tokenisation complete. */
    case TOKENIZE_COMPLETE = 'TK0';

    /** Fraud check completed without errors. */
    case FRAUD_CHECK_COMPLETE = 'FC0';

    /** FPI: authenticate payer. Seen on SPI only if the flow stalled here. */
    case AUTHENTICATE_PAYER = '3D4';

    /** FPI: fingerprint payer. Seen on SPI only if the flow stalled here. */
    case FINGERPRINT_PAYER = '3D5';

    /** FPI: challenge payer. Seen on SPI only if the flow stalled here. */
    case CHALLENGE_PAYER = '3D6';

    // ---------------------------------------------------------------------
    // ISO 8583 codes (financial requests)
    // ---------------------------------------------------------------------

    case APPROVED                  = '00';
    case REFER_TO_ISSUER           = '01';
    case INVALID_MERCHANT          = '03';
    case DO_NOT_HONOUR             = '05';
    case GENERAL_ERROR             = '06';
    case HONOR_WITH_ID             = '08';
    case INVALID_TRANSACTION       = '12';
    case INVALID_AMOUNT            = '13';
    case INVALID_CARD_NUMBER       = '14';
    case NO_SUCH_ISSUER            = '15';
    case INSUFFICIENT_FUNDS        = '51';
    case EXPIRED_CARD              = '54';
    case INCORRECT_PIN             = '55';
    case TRANSACTION_NOT_PERMITTED = '57';
    case EXCEEDS_LIMIT             = '61';
    case RESTRICTED_CARD           = '62';
    case SECURITY_VIOLATION        = '63';
    case INVALID_CVV               = '82';

    /**
     * True when the issuer approved a financial request.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * True when the gateway has returned RedirectData that must be rendered in
     * an iframe before the transaction can continue.
     *
     * This is the check that drives the SPI two-step flow — SP4 for a standard
     * SPI transaction, HP0 when a Hosted Payment Page is involved.
     */
    public function requiresRedirect(): bool
    {
        return $this === self::SPI_PREPROCESSING_COMPLETE
            || $this === self::HPP_PREPROCESSING_COMPLETE;
    }

    /**
     * True for a successfully completed non-financial step.
     *
     * These are not approvals and not declines — no funds have moved. 3D1 counts
     * as success: the card simply does not support 3DS2, and the caller may
     * proceed as standard e-commerce.
     */
    public function isNonFinancialSuccess(): bool
    {
        return match ($this) {
            self::THREE_DS_COMPLETE,
            self::THREE_DS_NOT_SUPPORTED,
            self::TOKENIZE_COMPLETE,
            self::FRAUD_CHECK_COMPLETE => true,
            default                    => false,
        };
    }

    /**
     * True only for an outright failure — neither an approval, nor a pending
     * redirect, nor a completed non-financial step.
     */
    public function isDeclined(): bool
    {
        return !$this->isApproved()
            && !$this->requiresRedirect()
            && !$this->isNonFinancialSuccess();
    }

    public function label(): string
    {
        return match ($this) {
            self::SPI_PREPROCESSING_COMPLETE => 'SPI preprocessing complete',
            self::HPP_PREPROCESSING_COMPLETE => 'HPP preprocessing complete',
            self::THREE_DS_COMPLETE          => '3DS complete',
            self::THREE_DS_NOT_SUPPORTED     => '3DS not supported',
            self::TOKENIZE_COMPLETE          => 'Tokenize complete',
            self::FRAUD_CHECK_COMPLETE       => 'Fraud check complete',
            self::AUTHENTICATE_PAYER         => 'Authenticate payer',
            self::FINGERPRINT_PAYER          => 'Fingerprint payer',
            self::CHALLENGE_PAYER            => 'Challenge payer',
            self::APPROVED                   => 'Transaction is approved',
            self::REFER_TO_ISSUER            => 'Refer to card issuer',
            self::INVALID_MERCHANT           => 'Invalid merchant',
            self::DO_NOT_HONOUR              => 'Do not honour',
            self::GENERAL_ERROR              => 'General error',
            self::HONOR_WITH_ID              => 'Honour with identification',
            self::INVALID_TRANSACTION        => 'Invalid transaction',
            self::INVALID_AMOUNT             => 'Invalid amount',
            self::INVALID_CARD_NUMBER        => 'Invalid card number',
            self::NO_SUCH_ISSUER             => 'No such issuer',
            self::INSUFFICIENT_FUNDS         => 'Insufficient funds',
            self::EXPIRED_CARD               => 'Expired card',
            self::INCORRECT_PIN              => 'Incorrect PIN',
            self::TRANSACTION_NOT_PERMITTED  => 'Transaction not permitted',
            self::EXCEEDS_LIMIT              => 'Exceeds withdrawal limit',
            self::RESTRICTED_CARD            => 'Restricted card',
            self::SECURITY_VIOLATION         => 'Security violation',
            self::INVALID_CVV                => 'Invalid CVV',
        };
    }
}
