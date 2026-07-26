<?php

declare(strict_types=1);

/**
 * Example: Full 3DS 2.x flow.
 *
 * This script demonstrates the two-step 3DS flow conceptually.
 * In a real web app:
 *   - Step 1 runs server-side when the customer submits the payment form.
 *   - The RedirectData is rendered in an iframe on the page.
 *   - PowerTranz POSTs the authentication result to your MerchantResponseUrl.
 *   - Step 2 runs in that callback handler with the SpiToken from the session.
 *
 * Run:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password \
 *   MERCHANT_RESPONSE_URL=https://your-site/3ds/callback php examples/three_ds_flow.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Money\Money;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ExtendedData;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\PaymentRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$client = new PowerTranzClient(
    getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID required'),
    getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD required'),
);

// PowerTranz POSTs the 3DS result here, and redirects the cardholder back to it.
// It must be a URL PowerTranz can reach — localhost will not work.
$merchantResponseUrl = getenv('MERCHANT_RESPONSE_URL')
    ?: throw new \RuntimeException('MERCHANT_RESPONSE_URL required');

// -----------------------------------------------------------------------
// Step 1: Initiate the sale with 3DS enabled.
//
// The top-level ThreeDSecure flag switches authentication on; the parameters
// and the callback URL travel in ExtendedData. No browser/device details are
// sent — the gateway handles fingerprinting inside the iframe.
// -----------------------------------------------------------------------
$sale = new SaleRequest(
    totalAmount:     Money::of('75.00', 'USD'),
    orderIdentifier: 'order-' . uniqid(),
    source:          new CardSource('4012000000020006', '2512', '323', 'John Doe'),
    threeDSecure:    true,
    extendedData:    ExtendedData::forThreeDSecure(
        merchantResponseUrl: $merchantResponseUrl,
        threeDSecure:        new ThreeDSecure(
            challengeWindowSize: ThreeDSecure::WINDOW_600x400,
            challengeIndicator:  ThreeDSecure::CHALLENGE_NO_PREFERENCE,
        ),
    ),
);

$result = $client->spi->sale($sale);

if (!$result instanceof ThreeDSecureChallenge) {
    // The gateway skipped preprocessing entirely — nothing to render.
    echo $result->approved
        ? "✓ Approved without redirect: {$result->transactionIdentifier}\n"
        : "✗ Declined: {$result->responseMessage} ({$result->isoResponseCode->value})\n";
    exit(0);
}

// IsoResponseCode SP4 — preprocessing complete, redirect pending.
echo "Redirect pending ({$result->responseMessage}).\n";
echo "SpiToken: {$result->spiToken} (valid 5 minutes)\n\n";
echo "Store the token in the session and render this in your checkout page:\n\n";
echo $result->iframe() . "\n\n";

// -----------------------------------------------------------------------
// Step 2: Runs in your MerchantResponseUrl handler.
//
// The iframe posts the 3DS result there (IsoResponseCode 3D0 on success, or
// 3D1 when the card does not support 3DS2). Remove the iframe, then complete
// the payment with the SpiToken — within 5 minutes.
//
// Note the result POSTed to your callback is the *authentication* outcome, not
// a financial one. No funds move until the payment completion below.
// -----------------------------------------------------------------------
echo "Simulating payment completion with SpiToken...\n";

$payment = $client->spi->payment(new PaymentRequest($result->spiToken));

if ($payment->approved) {
    echo "✓ Payment complete: {$payment->transactionIdentifier}\n";
    echo "  Auth code: {$payment->authorizationCode}\n";
} else {
    echo "✗ Payment failed: {$payment->responseMessage} ({$payment->isoResponseCode->value})\n";
}
