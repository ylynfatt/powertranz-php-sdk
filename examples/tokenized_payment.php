<?php

declare(strict_types=1);

/**
 * Example: Tokenise a card, then charge the token.
 *
 * Tokenisation happens on /spi/riskmgmt — a non-financial request that moves no
 * money. The token comes back as PanToken, and is sent on later charges as
 * Source.Token.
 *
 * Run:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/tokenized_payment.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Money\Money;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\TokenSource;
use PowerTranz\Model\Request\RiskManagementRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$client = new PowerTranzClient(
    getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID required'),
    getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD required'),
);

// -----------------------------------------------------------------------
// Step 1: Tokenise the card with a non-financial RiskMgmt request.
//
// Tokenize is only accepted on this endpoint — not on sale or auth. No funds
// move, so the amount is nominal and exists only to shape the request.
// -----------------------------------------------------------------------
echo "--- Tokenising card via /spi/riskmgmt ---\n";

$tokenise = new RiskManagementRequest(
    totalAmount:     Money::of('1.00', 'USD'),
    orderIdentifier: 'tokenise-' . uniqid(),
    source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
    tokenize:        true,
);

$result = $client->spi->riskManagement($tokenise);

if ($result instanceof ThreeDSecureChallenge) {
    echo "Redirect pending — see examples/three_ds_flow.php\n";
    exit(1);
}

// TK0 means tokenisation completed. This is not a financial approval, so
// $result->approved is false — check the token instead.
$panToken = $result->panToken
    ?? throw new \RuntimeException(
        "No PanToken returned: {$result->responseMessage} ({$result->isoResponseCode->value})"
    );

echo "✓ Tokenised ({$result->responseMessage}). PanToken: {$panToken}\n";
echo "  Store this against the customer record — never the card number.\n\n";

// -----------------------------------------------------------------------
// Step 2: Charge the stored token.
//
// Note the field flip: the gateway returns the token as PanToken but expects it
// back as Source.Token. TokenSource handles that.
// -----------------------------------------------------------------------
echo "--- Charging the stored token ---\n";

$repeatSale = new SaleRequest(
    totalAmount:     Money::of('15.00', 'USD'),
    orderIdentifier: 'order-' . uniqid(),
    source:          new TokenSource($panToken),
    // transactionIdentifier omitted — a UUID v4 is generated automatically.
    // For a First Atlantic Commerce token use TokenSource::fac($panToken).
);

$repeatResult = $client->spi->sale($repeatSale);

if ($repeatResult instanceof ThreeDSecureChallenge) {
    echo "Redirect pending on the token charge.\n";
    exit(1);
}

if ($repeatResult->approved) {
    echo "✓ Token charge approved: {$repeatResult->transactionIdentifier}\n";
    echo "  Auth code: {$repeatResult->authorizationCode}\n";
} else {
    echo "✗ Token charge declined: {$repeatResult->responseMessage} ({$repeatResult->isoResponseCode->value})\n";
}
