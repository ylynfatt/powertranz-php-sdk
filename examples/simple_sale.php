<?php

declare(strict_types=1);

/**
 * Example: Simple sale (no 3DS).
 *
 * Run against sandbox:
 *   POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/simple_sale.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Brick\Money\Money;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\PowerTranzClient;

$powerTranzId       = getenv('POWERTRANZ_ID')       ?: throw new \RuntimeException('POWERTRANZ_ID env var required');
$powerTranzPassword = getenv('POWERTRANZ_PASSWORD') ?: throw new \RuntimeException('POWERTRANZ_PASSWORD env var required');

$client = new PowerTranzClient($powerTranzId, $powerTranzPassword);

$sale = new SaleRequest(
    totalAmount:     Money::of('29.99', 'USD'),
    orderIdentifier: 'order-' . uniqid(),
    source:          new CardSource(
        cardPan:        '4111111111111111',
        cardExpiration: '2512',
        cardCvv:        '123',
        cardholderName: 'Jane Doe',
    ),
);

$result = $client->spi->sale($sale);

if ($result instanceof ThreeDSecureChallenge) {
    echo "3DS challenge required — this example does not handle 3DS.\n";
    echo "See examples/three_ds_flow.php\n";
    exit(1);
}

if ($result->approved) {
    echo "✓ Sale approved!\n";
    echo "  Transaction: {$result->transactionIdentifier}\n";
    echo "  Auth code:   {$result->authorizationCode}\n";
    echo "  Reference:   {$result->referenceNumber}\n";
    echo "  Amount:      {$result->totalAmount}\n";
} else {
    echo "✗ Sale declined: {$result->responseMessage} ({$result->isoResponseCode->value})\n";
}
