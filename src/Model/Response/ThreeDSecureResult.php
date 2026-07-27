<?php

declare(strict_types=1);

namespace PowerTranz\Model\Response;

use PowerTranz\Enum\IsoResponseCode;

/**
 * The 3DS authentication result POSTed by PowerTranz to your MerchantResponseUrl.
 *
 * ## The wire format is not what the docs' example suggests
 *
 * The integration guide shows this result as a JSON document, which reads as
 * though the gateway POSTs a JSON body. It does not. The request arrives
 * {@code application/x-www-form-urlencoded} with three fields:
 *
 *     Response=<the JSON document, as a string>
 *     TransactionIdentifier=<guid>
 *     SpiToken=<token>
 *
 * So the authentication detail — IsoResponseCode, ResponseMessage, the
 * RiskManagement.ThreeDSecure block, PanToken — is nested inside the
 * {@code Response} field and is not readable from {@code $_POST} directly.
 * Handlers that look for those keys at the top level find nothing.
 *
 * {@see fromCallback()} unwraps that for you:
 *
 *     $result = ThreeDSecureResult::fromCallback($_POST);
 *
 *     if ($result->isAuthenticated()) {
 *         $payment = $client->spi->payment(new PaymentRequest($result->spiToken));
 *     }
 *
 * @see https://developer.powertranz.com/docs/spi-3ds-1
 */
final class ThreeDSecureResult
{
    /**
     * @param array<string, mixed> $raw The decoded Response document.
     */
    private function __construct(
        public readonly string $spiToken,
        public readonly string $transactionIdentifier,
        public readonly ?IsoResponseCode $isoResponseCode,
        public readonly string $responseMessage,
        public readonly ?string $orderIdentifier,
        public readonly ?string $panToken,
        public readonly ?string $cardBrand,

        /** 3DS authentication status: 'Y' authenticated, 'A' attempted, 'N' failed, 'U' unavailable. */
        public readonly ?string $authenticationStatus,

        /** Electronic Commerce Indicator, e.g. '05'. */
        public readonly ?string $eci,

        public readonly ?string $cavv,
        public readonly ?string $protocolVersion,
        public readonly ?string $dsTransId,

        /** Message the issuer wants shown to the cardholder, when present. */
        public readonly ?string $cardholderInfo,

        public readonly array $raw,
    ) {
    }

    /**
     * Build from the POST body PowerTranz sends to your MerchantResponseUrl.
     *
     * Pass {@code $_POST}. A JSON body is also accepted, and the nested
     * {@code Response} field may arrive either as a JSON string or already
     * decoded — all three shapes are handled.
     *
     * @param array<string, mixed> $post
     */
    public static function fromCallback(array $post): self
    {
        $get = static function (array $source, string $name): mixed {
            foreach ($source as $key => $value) {
                if (strcasecmp((string) $key, $name) === 0) {
                    return $value;
                }
            }

            return null;
        };

        // Unwrap the nested Response document, whichever form it takes.
        $response = $get($post, 'Response');

        if (is_string($response)) {
            $decoded  = json_decode($response, true);
            $response = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($response)) {
            // Some flows may post the fields flat; fall back to the outer body.
            $response = $post;
        }

        $threeDs = [];
        $risk    = $get($response, 'RiskManagement');

        if (is_string($risk)) {
            $decoded = json_decode($risk, true);
            $risk    = is_array($decoded) ? $decoded : [];
        }

        if (is_array($risk)) {
            $nested  = $get($risk, 'ThreeDSecure');
            $threeDs = is_array($nested) ? $nested : [];
        }

        $str = static function (mixed $value): ?string {
            return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
        };

        // SpiToken and TransactionIdentifier are top-level fields on the POST,
        // but fall back to the inner document in case a flow nests them.
        $spiToken = $str($get($post, 'SpiToken')) ?? $str($get($response, 'SpiToken')) ?? '';
        $txId     = $str($get($post, 'TransactionIdentifier'))
            ?? $str($get($response, 'TransactionIdentifier'))
            ?? '';

        return new self(
            spiToken:              $spiToken,
            transactionIdentifier: $txId,
            isoResponseCode:       IsoResponseCode::tryFrom((string) ($str($get($response, 'IsoResponseCode')) ?? '')),
            responseMessage:       $str($get($response, 'ResponseMessage')) ?? '',
            orderIdentifier:       $str($get($response, 'OrderIdentifier')),
            panToken:              $str($get($response, 'PanToken')),
            cardBrand:             $str($get($response, 'CardBrand')),
            authenticationStatus:  $str($get($threeDs, 'AuthenticationStatus')),
            eci:                   $str($get($threeDs, 'Eci')),
            cavv:                  $str($get($threeDs, 'Cavv')),
            protocolVersion:       $str($get($threeDs, 'ProtocolVersion')),
            dsTransId:             $str($get($threeDs, 'DsTransId')),
            cardholderInfo:        $str($get($threeDs, 'CardholderInfo')),
            raw:                   $response,
        );
    }

    /**
     * True when the cardholder was authenticated, or authentication was attempted
     * with liability shift ('A').
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticationStatus === 'Y' || $this->authenticationStatus === 'A';
    }

    /**
     * True when the card does not support 3DS2 (3D1). The payment may still be
     * completed, but it proceeds as standard e-commerce without liability shift.
     */
    public function isThreeDsUnsupported(): bool
    {
        return $this->isoResponseCode === IsoResponseCode::THREE_DS_NOT_SUPPORTED;
    }

    /**
     * True when it is worth calling /spi/payment.
     *
     * The gateway declines a completion by default if authentication failed, so
     * this is a courtesy check rather than a guarantee.
     */
    public function canCompletePayment(): bool
    {
        return $this->spiToken !== ''
            && ($this->isAuthenticated() || $this->isThreeDsUnsupported());
    }

    /**
     * Any field from the decoded Response document, for values not modelled here.
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }
}
