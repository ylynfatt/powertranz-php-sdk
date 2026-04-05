<?php

declare(strict_types=1);

/**
 * Example: Authorise first, then capture separately.
 *
 * Useful for "charge on ship" flows where you reserve funds at order time
 * and capture when the item ships.
 *
 * Run:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/authorize_and_capture.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PowerTranz\Enum\CurrencyCode;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\CaptureRequest;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$client = new PowerTranzClient(
    getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID required'),
    getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD required'),
);

// Step 1: Authorise (reserve funds)
$auth = new AuthRequest(
    totalAmount:           150.00,
    currencyCode:          CurrencyCode::USD,
    orderIdentifier:       'order-' . uniqid(),
    transactionIdentifier: bin2hex(random_bytes(16)),
    source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
);

$authResult = $client->spi->authorize($auth);

if ($authResult instanceof ThreeDSecureChallenge) {
    echo "3DS required — see examples/three_ds_flow.php\n";
    exit(1);
}

if (!$authResult->approved) {
    echo "✗ Auth declined: {$authResult->responseMessage}\n";
    exit(1);
}

echo "✓ Authorised: {$authResult->transactionIdentifier}\n";
$authorisedTxnId = $authResult->transactionIdentifier;

// Step 2: Capture (in a real app, this would happen when the order ships)
$captureResult = $client->transactions->capture(new CaptureRequest(
    transactionIdentifier: $authorisedTxnId,
    totalAmount:           150.00,
));

if ($captureResult->approved) {
    echo "✓ Captured: {$captureResult->transactionIdentifier}\n";
} else {
    echo "✗ Capture failed: {$captureResult->responseMessage}\n";
}
