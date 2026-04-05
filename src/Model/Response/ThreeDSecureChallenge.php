<?php

declare(strict_types=1);

namespace PowerTranz\Model\Response;

/**
 * Returned when the gateway responds with IsoResponseCode '3D0'.
 *
 * The cardholder must complete the 3DS challenge before the payment can be
 * finalised. Render {@see $redirectHtml} in your page (typically an iframe),
 * then send a {@see \PowerTranz\Model\Request\PaymentRequest} with the
 * {@see $spiToken} once the challenge is complete.
 *
 * SpiTokens are valid for 5 minutes.
 *
 * Example:
 *   $challenge = $client->spi->sale($saleRequest);
 *   if ($challenge instanceof ThreeDSecureChallenge) {
 *       $_SESSION['spi_token'] = $challenge->spiToken;
 *       echo $challenge->render();
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
            responseMessage:       (string) ($data['ResponseMessage'] ?? '3DS challenge required'),
        );
    }

    /**
     * Returns true if the redirect content contains an iframe element.
     */
    public function isIframe(): bool
    {
        return stripos($this->redirectHtml, '<iframe') !== false;
    }

    /**
     * Returns the raw HTML for embedding in a checkout page.
     *
     * IMPORTANT: This HTML is provided by the PowerTranz gateway and is trusted
     * content for 3DS authentication. Do not pass arbitrary user input here.
     */
    public function render(): string
    {
        return $this->redirectHtml;
    }
}
