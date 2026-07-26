# PowerTranz PHP SDK

Unofficial PHP SDK for the [PowerTranz](https://powertranz.com/) payment gateway — SPI transactions, 3-D Secure 2.x, tokenisation, and Hosted Payment Pages.

[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

> This is an independent, community-maintained SDK. It is not affiliated with, sanctioned by, or supported by PowerTranz. For official support, contact PowerTranz directly.

## Requirements

- PHP 8.1 or higher
- `ext-curl`

## Installation

This package is not on Packagist yet. Install it from the repository by adding it as a VCS source in your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ylynfatt/powertranz-php-sdk.git"
        }
    ]
}
```

Then require it:

```bash
composer require ylynfatt/powertranz-php-sdk:dev-main
```

The SDK ships with a built-in cURL client, so no additional HTTP dependency is required. To use your own PSR-18 client instead, see [Custom HTTP client](#custom-http-client).

## Quick start

```php
use Brick\Money\Money;
use PowerTranz\PowerTranzClient;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Response\ThreeDSecureChallenge;

$client = new PowerTranzClient('merchant-id', 'password');

$result = $client->spi->sale(new SaleRequest(
    totalAmount:     Money::of('29.99', 'USD'),
    orderIdentifier: 'order-1234',
    source:          new CardSource(
        cardPan:        '4111111111111111',
        cardExpiration: '2512',   // YYMM
        cardCvv:        '123',
        cardholderName: 'Jane Doe',
    ),
));

if ($result instanceof ThreeDSecureChallenge) {
    // Cardholder must complete a 3DS challenge — see the 3-D Secure section.
    echo $result->render();
    exit;
}

echo $result->approved
    ? "Approved: {$result->transactionIdentifier}"
    : "Declined: {$result->responseMessage}";
```

The client defaults to the **sandbox** environment. Pass `Environment::PRODUCTION` to go live.

## Configuration

```php
use PowerTranz\Config\Environment;

$client = new PowerTranzClient('merchant-id', 'password', [
    'environment'     => Environment::PRODUCTION,
    'timeout'         => 30,   // seconds; must be >= 1
    'connect_timeout' => 10,
    'max_retries'     => 3,    // 0 disables retries
    'logger'          => $psrLogger,
]);
```

| Option | Default | Notes |
|---|---|---|
| `environment` | `Environment::SANDBOX` | `SANDBOX` → `staging.ptranz.com`, `PRODUCTION` → `api.ptranz.com` |
| `timeout` | `30` | Total request timeout in seconds |
| `connect_timeout` | `10` | Connection timeout in seconds |
| `max_retries` | `3` | Retries with exponential backoff |
| `logger` | `NullLogger` | Any PSR-3 logger |

For DI containers, build a `Configuration` and hand it over:

```php
use PowerTranz\Config\ConfigurationBuilder;

$config = ConfigurationBuilder::create()
    ->withCredentials('merchant-id', 'password')
    ->withProductionEnvironment()
    ->withMaxRetries(5)
    ->build();

$client = PowerTranzClient::fromConfiguration($config);
```

> **Note:** `fromConfiguration()` currently forwards only the environment, timeouts, and retry count. A logger or custom retry delay set on the `Configuration` is not carried over — pass `logger` through the constructor options instead.

### Custom HTTP client

Supply a PSR-18 client along with PSR-17 request and stream factories. All three are required together — passing `http_client` without the factories throws `InvalidArgumentException`.

```php
$psr17  = new \Nyholm\Psr7\Factory\Psr17Factory();

$client = new PowerTranzClient('merchant-id', 'password', [
    'http_client'     => new \GuzzleHttp\Client(),
    'request_factory' => $psr17,
    'stream_factory'  => $psr17,
]);
```

## Services

The client exposes three services as readonly properties.

### `$client->spi` — charges and authentication

| Method | Returns | Purpose |
|---|---|---|
| `sale(SaleRequest)` | `SaleResponse\|ThreeDSecureChallenge` | Authorise and capture in one step |
| `authorize(AuthRequest)` | `AuthResponse\|ThreeDSecureChallenge` | Reserve funds without capturing |
| `riskManagement(RiskManagementRequest)` | `RiskManagementResponse\|ThreeDSecureChallenge` | Non-financial 3DS pre-auth and fraud check |
| `payment(PaymentRequest)` | `PaymentResponse` | Complete a payment after a 3DS challenge |

The union return types are deliberate: static analysis forces you to handle the 3DS branch, so a challenge can't be silently treated as an approval.

### `$client->transactions` — post-authorisation

| Method | Returns | Purpose |
|---|---|---|
| `capture(CaptureRequest)` | `CaptureResponse` | Capture authorised funds (supports partial) |
| `refund(RefundRequest)` | `RefundResponse` | Refund a settled transaction (supports partial) |
| `void(VoidRequest)` | `VoidResponse` | Cancel before settlement |

### `$client->hostedPage` — HPP

```php
$url = $client->hostedPage->buildRedirectUrl(
    orderIdentifier: 'order-1234',
    totalAmount:     Money::of('50.00', 'USD'),
    returnUrl:       'https://example.com/payment/return',
);

header("Location: {$url}");
```

## Money and currency

All monetary values are [`Brick\Money\Money`](https://github.com/brick/money) objects — arbitrary-precision decimals, no float rounding. Currency travels with the amount, so no separate currency argument is ever needed.

```php
use Brick\Money\Money;
use PowerTranz\Enum\CurrencyCode;

Money::of('29.99', 'USD');
CurrencyCode::TTD->money('150.00');   // convenience helper
```

Amounts must be strictly greater than zero; this is enforced by the `PositiveMoney` constraint on every request that carries an amount.

On responses, `$response->totalAmount` is a `Money` — or `null` if the gateway returns a currency code the SDK doesn't recognise. The raw value is always available via `$response->getRaw('TotalAmount')`.

## 3-D Secure 2.x

A `ThreeDSecureChallenge` is returned when the issuer requires cardholder authentication (ISO response code `3D0`).

**Step 1 — initiate with browser details** (collected client-side via JavaScript):

```php
use PowerTranz\Model\Request\Parts\{BrowserDetails, ThreeDSecure};

$sale = new SaleRequest(
    totalAmount:     Money::of('75.00', 'USD'),
    orderIdentifier: 'order-1234',
    source:          $cardSource,
    threeDSecure:    ThreeDSecure::withBrowser(new BrowserDetails(
        acceptHeader: $_SERVER['HTTP_ACCEPT'],
        colorDepth:   '24',
        javaEnabled:  false,
        language:     'en-US',
        screenHeight: 900,
        screenWidth:  1440,
        timeZone:     '-300',
        userAgent:    $_SERVER['HTTP_USER_AGENT'],
    )),
);

$result = $client->spi->sale($sale);

if ($result instanceof ThreeDSecureChallenge) {
    $_SESSION['spi_token'] = $result->spiToken;   // expires in 5 minutes
    echo $result->render();                        // renders the issuer's challenge
    exit;
}
```

**Step 2 — complete in your callback handler**, once the issuer posts back:

```php
$payment = $client->spi->payment(new PaymentRequest($_SESSION['spi_token']));

echo $payment->approved
    ? "Approved: {$payment->transactionIdentifier}"
    : "Failed: {$payment->responseMessage}";
```

If the issuer authenticates without a challenge (frictionless flow), step 1 returns a normal `SaleResponse` and step 2 is skipped entirely.

## Tokenisation

Set `tokenize: true` on any SPI request; the resulting token comes back on the response as `$response->panToken`.

```php
$result = $client->spi->sale(new SaleRequest(
    totalAmount:     Money::of('19.99', 'USD'),
    orderIdentifier: 'order-1234',
    source:          $cardSource,
    tokenize:        true,
));

$token = $result->panToken;   // store this, never the PAN
```

Charge it later with a `TokenSource`:

```php
use PowerTranz\Model\Request\Parts\TokenSource;

$client->spi->sale(new SaleRequest(
    totalAmount:     Money::of('19.99', 'USD'),
    orderIdentifier: 'order-5678',
    source:          new TokenSource(panToken: $token),
));
```

## Error handling

All exceptions extend `PowerTranzException` (itself a `RuntimeException`).

| Exception | Raised when |
|---|---|
| `ValidationException` | A request fails local validation — never reaches the network |
| `AuthenticationException` | Credentials rejected (extends `ApiException`) |
| `ApiException` | Gateway returned an error; `getHttpStatus()`, `getResponseBody()` |
| `TokenExpiredException` | SpiToken used after its 5-minute window |
| `NetworkException` | Connection failure, timeout, retries exhausted |

`ValidationException::getErrors()` returns a map keyed by field name, with **all** violations collected in a single pass:

```php
use PowerTranz\Exception\ValidationException;

try {
    $client->spi->sale($sale);
} catch (ValidationException $e) {
    foreach ($e->getErrors() as $field => $message) {
        echo "{$field}: {$message}\n";
    }
    // totalAmount: TotalAmount must be greater than zero.
    // orderIdentifier: OrderIdentifier must not be empty.
}
```

A **declined** transaction is not an exception. Check `$response->approved` and inspect `$response->responseMessage` / `$response->isoResponseCode`.

## Responses

Every response extends `SpiResponse` and exposes:

`isoResponseCode`, `responseCode`, `responseMessage`, `transactionIdentifier`, `orderIdentifier`, `referenceNumber`, `authorizationCode`, `panToken`, `spiToken`, `cardBrand`, `transactionType`, `totalAmount`, `approved`, `requiresThreeDsChallenge`

Fields the SDK doesn't model yet remain reachable via `getRaw('FieldName')`, so a gateway-side addition never blocks you.

## Examples

Runnable scripts in [`examples/`](examples):

```bash
POWERTRANZ_ID=your-id POWERTRANZ_PASSWORD=your-password php examples/simple_sale.php
```

- [`simple_sale.php`](examples/simple_sale.php) — one-step sale
- [`authorize_and_capture.php`](examples/authorize_and_capture.php) — two-step flow
- [`three_ds_flow.php`](examples/three_ds_flow.php) — full 3DS 2.x flow
- [`tokenized_payment.php`](examples/tokenized_payment.php) — tokenise, then charge the token

## Security

- **Never log or persist a PAN or CVV.** Store the `panToken` instead.
- Use HPP (`$client->hostedPage`) to keep card entry off your servers and reduce PCI DSS scope.
- Keep credentials in environment variables or a secrets manager — never in version control.
- Sandbox and production credentials are not interchangeable.

## Development

```bash
composer install
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix
```

## License

This SDK is open-sourced software licensed under the [MIT license](LICENSE).
