<?php

declare(strict_types=1);

/**
 * Example: Full 3DS 2.x flow.
 *
 * This script demonstrates the two-step 3DS flow conceptually.
 * In a real web app:
 *   - Step 1 runs server-side when the customer submits the payment form.
 *   - The challenge HTML is embedded in the page.
 *   - The 3DS issuer posts the result back to your callback URL.
 *   - Step 2 runs in the callback handler with the SpiToken from the session.
 *
 * Run:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/three_ds_flow.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Model\Request\Parts\BrowserDetails;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\PaymentRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$client = new PowerTranzClient(
    getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID required'),
    getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD required'),
);

// -----------------------------------------------------------------------
// Step 1: Initiate the sale with 3DS enabled.
//
// BrowserDetails are normally collected via JavaScript on the checkout page
// and sent to your server with the payment form submission.
// -----------------------------------------------------------------------
$browserDetails = new BrowserDetails(
    acceptHeader:  'text/html,application/xhtml+xml',
    colorDepth:    '24',
    javaEnabled:   false,
    language:      'en-US',
    screenHeight:  900,
    screenWidth:   1440,
    timeZone:      '-300',
    userAgent:     'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
);

$sale = new SaleRequest(
    totalAmount:     75.00,
    currencyCode:    CurrencyCode::USD,
    orderIdentifier: 'order-' . uniqid(),
    source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
    threeDSecure:    ThreeDSecure::withBrowser($browserDetails),
);

$result = $client->spi->sale($sale);

if (!$result instanceof ThreeDSecureChallenge) {
    // Frictionless flow: issuer authenticated without a challenge
    echo $result->approved
        ? "✓ Frictionless 3DS — approved: {$result->transactionIdentifier}\n"
        : "✗ Declined (frictionless): {$result->responseMessage}\n";
    exit(0);
}

// 3DS challenge required
echo "3DS challenge required.\n";
echo "SpiToken: {$result->spiToken} (valid 5 minutes)\n";
echo "In a real app, store this token in the session and render the HTML below:\n\n";
echo $result->render() . "\n\n";

// -----------------------------------------------------------------------
// Step 2: After the cardholder completes the 3DS challenge, your callback
// handler receives a POST from the 3DS issuer. Retrieve the SpiToken from
// your session and complete the payment.
//
// For this demo we simulate immediately (no real challenge occurs).
// -----------------------------------------------------------------------
echo "Simulating payment completion with SpiToken...\n";

$payment = $client->spi->payment(new PaymentRequest($result->spiToken));

if ($payment->approved) {
    echo "✓ Payment complete: {$payment->transactionIdentifier}\n";
    echo "  Auth code: {$payment->authorizationCode}\n";
} else {
    echo "✗ Payment failed: {$payment->responseMessage}\n";
}
