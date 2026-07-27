<?php

declare(strict_types=1);

namespace PowerTranz\Model\Response;

use PowerTranz\Enum\IsoResponseCode;

/**
 * Returned when the gateway responds with IsoResponseCode 'SP4' (or 'HP0' for a
 * Hosted Payment Page), meaning SPI preprocessing is complete and a redirect is
 * pending.
 *
 * Note that this is *not* signalled by '3D0'. That code means 3DS
 * authentication has finished, and it arrives later — POSTed by PowerTranz to
 * your MerchantResponseUrl, not on the response to the initial request.
 *
 * The flow from here:
 *   1. Render {@see iframe()} in the checkout page.
 *   2. The iframe talks to the 3DS servers and posts the result to your
 *      MerchantResponseUrl.
 *   3. Send a {@see \PowerTranz\Model\Request\PaymentRequest} with the
 *      {@see $spiToken} to complete the payment.
 *
 * SpiTokens are valid for 5 minutes.
 *
 * Example:
 *   $result = $client->spi->sale($saleRequest);
 *   if ($result instanceof ThreeDSecureChallenge) {
 *       $_SESSION['spi_token'] = $result->spiToken;
 *       echo $result->iframe();
 *   }
 */
final class ThreeDSecureChallenge
{
    public function __construct(
        public readonly string $spiToken,
        public readonly string $redirectHtml,
        public readonly string $transactionIdentifier,
        public readonly string $orderIdentifier,
        public readonly string $responseMessage,

        /**
         * The code that produced this challenge — SP4 for a standard SPI
         * transaction, HP0 when a Hosted Payment Page is involved.
         *
         * Retained so callers can tell the two apart and log the gateway's own
         * answer rather than inferring it from the object's type.
         */
        public readonly IsoResponseCode $isoResponseCode = IsoResponseCode::SPI_PREPROCESSING_COMPLETE,

        /**
         * The complete decoded response, so nothing the gateway sent is lost.
         *
         * @var array<string, mixed>
         */
        private readonly array $rawData = [],
    ) {
    }

    /**
     * Build a ThreeDSecureChallenge from the raw SPI response array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            spiToken:              (string) ($data['SpiToken'] ?? ''),
            redirectHtml:          (string) ($data['Redirect'] ?? $data['RedirectData'] ?? ''),
            transactionIdentifier: (string) ($data['TransactionIdentifier'] ?? ''),
            orderIdentifier:       (string) ($data['OrderIdentifier'] ?? ''),
            responseMessage:       (string) ($data['ResponseMessage'] ?? 'SPI Preprocessing complete'),
            isoResponseCode:       IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''))
                ?? IsoResponseCode::SPI_PREPROCESSING_COMPLETE,
            rawData:               $data,
        );
    }

    /**
     * Any field from the gateway response, including ones this class does not
     * model — TotalAmount, CurrencyCode, TransactionType and so on.
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->rawData[$key] ?? $default;
    }

    /**
     * The complete decoded response exactly as the gateway sent it.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->rawData;
    }

    /**
     * The raw RedirectData document returned by the gateway.
     *
     * This is a complete HTML document containing a self-submitting form and
     * script — it is not itself an iframe, and it must be placed *inside* one.
     * Use {@see iframe()} unless you are building the wrapper yourself.
     *
     * IMPORTANT: This HTML comes from the PowerTranz gateway and is trusted
     * content for 3DS authentication. Never pass user input through here.
     */
    public function render(): string
    {
        return $this->redirectHtml;
    }

    /**
     * Wrap the RedirectData in the iframe documented by PowerTranz:
     *
     *   <iframe srcdoc="{RedirectData}" frameborder="0" width="100%" height="500"></iframe>
     *
     * The document is escaped for the srcdoc attribute, so the result is safe to
     * echo directly into a checkout page.
     *
     * Once the cardholder completes (or skips) the challenge, the iframe posts
     * the result to your MerchantResponseUrl. The iframe is short-lived — remove
     * it and redirect the parent window from your callback page.
     */
    public function iframe(string $width = '100%', string $height = '500'): string
    {
        return sprintf(
            '<iframe srcdoc="%s" frameborder="0" width="%s" height="%s"></iframe>',
            htmlspecialchars($this->redirectHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($width, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($height, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }
}
