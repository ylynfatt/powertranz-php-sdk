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
 *  - **ISO 8583 codes** (00, 05, 51, 91, …) are the financial answer, assigned in
 *    most cases by the issuing bank but sometimes by the acquirer or the card
 *    network. PowerTranz itself does not decline a financial authorisation.
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
 * MerchantResponseUrl, and *that* payload carries 3D0.
 *
 * ## Unrecognised codes
 *
 * Card networks add codes over time, so {@see tryFrom()} can legitimately return
 * null. Never substitute a specific code as a fallback: reporting 91 ("issuer
 * inoperative", worth retrying) as 05 ("do not honour", do not retry) tells the
 * merchant something materially untrue. {@see \PowerTranz\Model\Response\SpiResponse}
 * keeps the raw string alongside the enum for exactly this reason.
 *
 * @see https://developer.powertranz.com/docs/isoresponsecode
 * @see https://developer.powertranz.com/docs/iso8583-response-codes
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
    // ISO 8583 financial codes
    //
    // Case names carry a numeric suffix where the gateway documents the same
    // description for more than one code, so each name stays unambiguous.
    // ---------------------------------------------------------------------

    case APPROVED                        = '00';
    case REFER_TO_ISSUER                 = '01';
    case REFER_TO_ISSUER_SPECIAL         = '02';
    case INVALID_MERCHANT                = '03';
    case PICK_UP_CARD                    = '04';
    case DO_NOT_HONOUR                   = '05';
    case ERROR                           = '06';
    case PICK_UP_CARD_SPECIAL            = '07';
    case HONOR_WITH_ID                   = '08';
    case REQUEST_IN_PROGRESS             = '09';
    case APPROVED_PARTIAL_AMOUNT         = '10';
    case VIP_APPROVAL                    = '11';
    case INVALID_TRANSACTION             = '12';
    case INVALID_AMOUNT                  = '13';
    case CARD_NUMBER_DOES_NOT_EXIST      = '14';
    case NO_SUCH_ISSUER                  = '15';
    case APPROVED_UPDATE_TRACK_3         = '16';
    case CUSTOMER_CANCELLATION           = '17';
    case CUSTOMER_DISPUTE                = '18';
    case RE_ENTER_TRANSACTION            = '19';
    case INVALID_RESPONSE                = '20';
    case NO_ACTION_TAKEN                 = '21';
    case SUSPECTED_MALFUNCTION           = '22';
    case UNACCEPTABLE_TRANSACTION_FEE    = '23';
    case FILE_UPDATE_NOT_SUPPORTED       = '24';
    case UNABLE_TO_LOCATE_RECORD         = '25';
    case DUPLICATE_FILE_UPDATE_RECORD    = '26';
    case FILE_UPDATE_FIELD_EDIT_ERROR    = '27';
    case FILE_TEMPORARILY_UNAVAILABLE    = '28';
    case FILE_UPDATE_NOT_SUCCESSFUL      = '29';
    case FORMAT_ERROR_30                 = '30';
    case ISSUER_SIGN_OFF                 = '31';
    case COMPLETED_PARTIALLY             = '32';
    case EXPIRED_CARD_33                 = '33';
    case SUSPECTED_FRAUD_34              = '34';
    case CARD_ACCEPTOR_CONTACT_ACQUIRER_35 = '35';
    case RESTRICTED_CARD_36              = '36';
    case CARD_ACCEPTOR_CALL_ACQUIRER_37  = '37';
    case ALLOWABLE_PIN_TRIES_EXCEEDED    = '38';
    case NO_CREDIT_ACCOUNT               = '39';
    case FUNCTION_NOT_SUPPORTED          = '40';
    case PICK_UP_CARD_LOST               = '41';
    case NO_UNIVERSAL_ACCOUNT            = '42';
    case PICK_UP_CARD_STOLEN             = '43';
    case NO_INVESTMENT_ACCOUNT           = '44';
    case INSUFFICIENT_FUNDS              = '51';
    case NO_CHECKING_ACCOUNT             = '52';
    case NO_SAVINGS_ACCOUNT              = '53';
    case EXPIRED_CARD_54                 = '54';
    case INCORRECT_PIN                   = '55';
    case NO_CARD_RECORD                  = '56';
    case TRANSACTION_NOT_PERMITTED_57    = '57';
    case TRANSACTION_NOT_PERMITTED_58    = '58';
    case SUSPECTED_FRAUD_59              = '59';
    case CARD_ACCEPTOR_CONTACT_ACQUIRER_60 = '60';
    case EXCEEDS_LIMIT                   = '61';
    case RESTRICTED_CARD_62              = '62';
    case SECURITY_VIOLATION              = '63';
    case ORIGINAL_AMOUNT_INCORRECT       = '64';
    case ACTIVITY_COUNT_EXCEEDED         = '65';
    case CARD_ACCEPTOR_CALL_ACQUIRER_66  = '66';
    case CARD_PICK_UP_AT_ATM             = '67';
    case RESPONSE_RECEIVED_TOO_LATE      = '68';
    case TOO_MANY_WRONG_PIN_TRIES        = '75';
    case PREVIOUS_MESSAGE_NOT_FOUND      = '76';
    case DATA_DOES_NOT_MATCH_ORIGINAL    = '77';
    case INVALID_DATE                    = '80';
    case CRYPTOGRAPHIC_ERROR_IN_PIN      = '81';
    case INVALID_CVV                     = '82';
    case UNABLE_TO_VERIFY_PIN            = '83';
    case INVALID_AUTHORIZATION_LIFECYCLE = '84';
    case NO_REASON_TO_DECLINE            = '85';
    case PIN_VALIDATION_NOT_POSSIBLE     = '86';
    case CRYPTOGRAPHIC_FAILURE           = '88';
    case AUTHENTICATION_FAILURE          = '89';
    case CUTOFF_IN_PROCESS               = '90';
    case ISSUER_OR_SWITCH_INOPERATIVE    = '91';
    case NO_ROUTING_PATH                 = '92';
    case VIOLATION_OF_LAW                = '93';
    case DUPLICATE_TRANSMISSION          = '94';
    case RECONCILE_ERROR                 = '95';
    case SYSTEM_MALFUNCTION              = '96';
    case FORMAT_ERROR_97                 = '97';
    case HOST_UNREACHABLE                = '98';
    case ERRORED_TRANSACTION             = '99';
    case FORCE_STIP                      = 'N0';
    case CASH_SERVICE_NOT_AVAILABLE      = 'N3';
    case CASH_REQUEST_EXCEEDS_LIMIT      = 'N4';
    case DECLINE_FOR_CVV2_FAILURE        = 'N7';
    case INVALID_BILLER_INFORMATION      = 'P2';
    case PIN_CHANGE_UNBLOCK_DECLINED     = 'P5';
    case UNSAFE_PIN                      = 'P6';
    case FORWARD_TO_ISSUER_XA            = 'XA';
    case FORWARD_TO_ISSUER_XD            = 'XD';

    /**
     * Descriptions exactly as the gateway documents them.
     *
     */
    private const LABELS = [
        'SP4' => 'SPI preprocessing complete',
        'HP0' => 'HPP preprocessing complete',
        '3D0' => '3DS complete',
        '3D1' => '3DS not supported',
        'TK0' => 'Tokenize complete',
        'FC0' => 'Fraud check complete',
        '3D4' => 'Authenticate payer',
        '3D5' => 'Fingerprint payer',
        '3D6' => 'Challenge payer',
        '00'  => 'Approved',
        '01'  => 'Refer to issuer',
        '02'  => 'Refer to issuer (special)',
        '03'  => 'Invalid merchant',
        '04'  => 'Pick-up card',
        '05'  => 'Do not honor',
        '06'  => 'Error',
        '07'  => 'Pick-up card (special)',
        '08'  => 'Honor with identification',
        '09'  => 'Request in progress',
        '10'  => 'Approved for partial amount',
        '11'  => 'VIP Approval',
        '12'  => 'Invalid transaction',
        '13'  => 'Invalid amount',
        '14'  => 'Card number does not exist',
        '15'  => 'No such issuer',
        '16'  => 'Approved, update track 3',
        '17'  => 'Customer cancellation',
        '18'  => 'Customer dispute',
        '19'  => 'Re-enter transaction',
        '20'  => 'Invalid response',
        '21'  => 'No action taken (no match)',
        '22'  => 'Suspected malfunction',
        '23'  => 'Unacceptable transaction fee',
        '24'  => 'File update not supported by receiver',
        '25'  => 'Unable to locate record',
        '26'  => 'Duplicate file update record',
        '27'  => 'File update field edit error',
        '28'  => 'File temporarily unavailable',
        '29'  => 'File update not successful',
        '30'  => 'Format error',
        '31'  => 'Issuer sign-off',
        '32'  => 'Completed partially',
        '33'  => 'Expired card',
        '34'  => 'Suspected fraud',
        '35'  => 'Card acceptor contact acquirer',
        '36'  => 'Restricted card',
        '37'  => 'Card acceptor call acquirer',
        '38'  => 'Allowable PIN tries exceeded',
        '39'  => 'No credit account',
        '40'  => 'Function not supported',
        '41'  => 'Pick-up card (lost card)',
        '42'  => 'No universal account',
        '43'  => 'Pick-up card (stolen card)',
        '44'  => 'No investment account',
        '51'  => 'Not sufficient funds',
        '52'  => 'No checking account',
        '53'  => 'No savings account',
        '54'  => 'Expired card',
        '55'  => 'Incorrect PIN',
        '56'  => 'No card record',
        '57'  => 'Transaction not permitted to card',
        '58'  => 'Transaction not permitted to card',
        '59'  => 'Suspected fraud',
        '60'  => 'Card acceptor contact acquirer',
        '61'  => 'Exceeds withdrawal limit',
        '62'  => 'Restricted card',
        '63'  => 'Security violation',
        '64'  => 'Original amount incorrect',
        '65'  => 'Activity count exceeded',
        '66'  => 'Card acceptor call acquirer',
        '67'  => 'Card pick up at ATM',
        '68'  => 'Response received too late',
        '75'  => 'Too many wrong PIN tries',
        '76'  => 'Previous message not found',
        '77'  => 'Data does not match original message',
        '80'  => 'Invalid date',
        '81'  => 'Cryptographic error in PIN',
        '82'  => 'Incorrect CVV (Visa/Amex), Policy (MC)',
        '83'  => 'Unable to verify PIN (Visa/Amex), Fraud/Security (MC)',
        '84'  => 'Invalid authorization life cycle',
        '85'  => 'No reason to decline',
        '86'  => 'PIN validation not possible',
        '88'  => 'Cryptographic failure',
        '89'  => 'Authentication failure',
        '90'  => 'Cutoff is in process',
        '91'  => 'Issuer or switch inoperative',
        '92'  => 'No routing path',
        '93'  => 'Violation of law',
        '94'  => 'Duplicate transmission',
        '95'  => 'Reconcile error',
        '96'  => 'System malfunction',
        '97'  => 'Format Error',
        '98'  => 'Host Unreachable',
        '99'  => 'Errored Transaction',
        'N0'  => 'Force STIP',
        'N3'  => 'Cash Service Not Available',
        'N4'  => 'Cash request exceeds issuer limit',
        'N7'  => 'Decline for CVV2 failure',
        'P2'  => 'Invalid biller information',
        'P5'  => 'PIN Change Unblock Declined',
        'P6'  => 'Unsafe PIN',
        'XA'  => 'Forward to issuer',
        'XD'  => 'Forward to issuer',
    ];

    /**
     * Codes that indicate a transient condition — the issuer or network was
     * unreachable or busy rather than refusing the transaction.
     *
     * A retry may succeed. A flat decline like 05 will not, and retrying it
     * risks tripping issuer velocity rules.
     */
    private const RETRYABLE = ['09', '19', '28', '68', '90', '91', '92', '96', '98', 'N0'];

    /**
     * True when the issuer approved a financial request.
     *
     * Only 00 is an unqualified approval. 10, 11, 16 and 32 are approvals with
     * caveats — check for them explicitly if your flow supports partials.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * True when the gateway has returned RedirectData that must be rendered in
     * an iframe before the transaction can continue.
     */
    public function requiresRedirect(): bool
    {
        return $this === self::SPI_PREPROCESSING_COMPLETE
            || $this === self::HPP_PREPROCESSING_COMPLETE;
    }

    /**
     * True for a successfully completed non-financial step.
     *
     * These are neither approvals nor declines — no funds have moved. 3D1 counts
     * as success: the card simply does not support 3DS2.
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

    /**
     * True when the decline reflects a temporary systems condition rather than a
     * refusal, so a retry is reasonable.
     */
    public function isRetryable(): bool
    {
        return in_array($this->value, self::RETRYABLE, true);
    }

    /**
     * True for a code the issuer wants the card retained on.
     */
    public function requiresCardRetention(): bool
    {
        return match ($this) {
            self::PICK_UP_CARD,
            self::PICK_UP_CARD_SPECIAL,
            self::PICK_UP_CARD_LOST,
            self::PICK_UP_CARD_STOLEN,
            self::CARD_PICK_UP_AT_ATM => true,
            default                   => false,
        };
    }

    public function label(): string
    {
        return self::LABELS[$this->value] ?? $this->value;
    }
}
