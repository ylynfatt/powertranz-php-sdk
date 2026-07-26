<?php

declare(strict_types=1);

/**
 * Example: Tokenise a card and use the token for subsequent charges.
 *
 * First run: charge the card and receive a PanToken.
 * Subsequent runs: charge using the stored token (no card data needed).
 *
 * Run:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/tokenized_payment.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Money\Money;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\TokenSource;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$client = new PowerTranzClient(
    getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID required'),
    getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD required'),
);

// -----------------------------------------------------------------------
// First charge: use raw card details and tokenize.
// -----------------------------------------------------------------------
echo "--- Initial charge with tokenization ---\n";

$firstSale = new SaleRequest(
    totalAmount:     Money::of('15.00', 'USD'),
    orderIdentifier: 'order-' . uniqid(),
    source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
    tokenize:        true,
);

$firstResult = $client->spi->sale($firstSale);

if ($firstResult instanceof ThreeDSecureChallenge) {
    echo "3DS required for first charge — see examples/three_ds_flow.php\n";
    exit(1);
}

if (!$firstResult->approved) {
    echo "✗ First charge declined: {$firstResult->responseMessage}\n";
    exit(1);
}

// An approved response does not guarantee a token: tokenisation is a separate
// step and PanToken is absent if it did not run.
$panToken = $firstResult->panToken
    ?? throw new \RuntimeException('Charge approved but no PanToken was returned — was Tokenize enabled?');

echo "✓ Approved. PanToken: {$panToken}\n";
echo "  (Store this token in your database against the customer record)\n\n";

// -----------------------------------------------------------------------
// Subsequent charge: use the stored PanToken.
// No card data is transmitted — only the token.
// -----------------------------------------------------------------------
echo "--- Subsequent recurring charge with token ---\n";

$repeatSale = new SaleRequest(
    totalAmount:     Money::of('15.00', 'USD'),
    orderIdentifier: 'order-' . uniqid(),
    source:          new TokenSource($panToken),
    // transactionIdentifier omitted — a UUID v4 is generated automatically
);

$repeatResult = $client->spi->sale($repeatSale);

if ($repeatResult instanceof ThreeDSecureChallenge) {
    echo "3DS required for repeat charge.\n";
    exit(1);
}

if ($repeatResult->approved) {
    echo "✓ Repeat charge approved: {$repeatResult->transactionIdentifier}\n";
} else {
    echo "✗ Repeat charge declined: {$repeatResult->responseMessage}\n";
}
