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
    // A redirect is pending — render it in an iframe. See 3-D Secure below.
    echo $result->iframe();
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
| `riskManagement(RiskManagementRequest)` | `RiskManagementResponse\|ThreeDSecureChallenge` | Non-financial: 3DS authentication, fraud check, tokenisation |
| `payment(PaymentRequest)` | `PaymentResponse` | Complete the payment once authentication has finished |

The union return types are deliberate: static analysis forces you to handle the redirect branch, so a pending challenge can't be silently treated as an approval.

### `$client->transactions` — post-authorisation

| Method | Returns | Purpose |
|---|---|---|
| `capture(CaptureRequest)` | `CaptureResponse` | Capture authorised funds (supports partial) |
| `refund(RefundRequest)` | `RefundResponse` | Refund a settled transaction (supports partial) |
| `void(VoidRequest)` | `VoidResponse` | Cancel before settlement |

### `$client->hostedPage` — HPP

| Method | Returns | Purpose |
|---|---|---|
| `sale(...)` | `SaleResponse\|ThreeDSecureChallenge` | Hosted-page sale |
| `authorize(...)` | `AuthResponse\|ThreeDSecureChallenge` | Hosted-page authorisation |

See [Hosted Payment Pages](#hosted-payment-pages) for the full flow.

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

3DS is a **two-call flow**. The first call returns a `ThreeDSecureChallenge` carrying an HTML document to render in an iframe; the second completes the payment once authentication has finished.

You need a **`merchantResponseUrl`**: a publicly reachable URL on your site. PowerTranz POSTs the authentication result there and returns the cardholder to it. It is required — without it the cardholder finishes in the iframe and control never comes back.

**Step 1 — initiate.** Enable the `threeDSecure` flag and supply the parameters in `ExtendedData`:

```php
use PowerTranz\Model\Request\Parts\{ExtendedData, ThreeDSecure};

$result = $client->spi->sale(new SaleRequest(
    totalAmount:     Money::of('75.00', 'USD'),
    orderIdentifier: 'order-1234',
    source:          $cardSource,
    threeDSecure:    true,
    extendedData:    ExtendedData::forThreeDSecure(
        merchantResponseUrl: 'https://example.com/3ds/callback',
        threeDSecure:        new ThreeDSecure(
            challengeWindowSize: ThreeDSecure::WINDOW_FULLPAGE,
            challengeIndicator:  ThreeDSecure::CHALLENGE_NO_PREFERENCE,
        ),
    ),
));

if ($result instanceof ThreeDSecureChallenge) {
    $_SESSION['spi_token'] = $result->spiToken;   // expires in 5 minutes
    echo $result->iframe();                        // renders the gateway's document
    exit;
}
```

`iframe()` emits the wrapper PowerTranz documents, with the payload escaped for the `srcdoc` attribute. Use `render()` if you need the raw document to wrap yourself.

**Step 2 — complete, in your `merchantResponseUrl` handler.** Parse the POST with `ThreeDSecureResult::fromCallback()`, then complete the payment within five minutes:

```php
use PowerTranz\Model\Response\ThreeDSecureResult;

$result = ThreeDSecureResult::fromCallback($_POST);

if ($result->canCompletePayment()) {
    $payment = $client->spi->payment(new PaymentRequest($result->spiToken));

    echo $payment->approved
        ? "Approved: {$payment->transactionIdentifier}"
        : "Failed: {$payment->responseMessage}";
}
```

The result posted to your callback is the **authentication** outcome, not a financial one — no funds move until step 2.

> **The callback body is not JSON.** The integration guide shows the result as a JSON document, but it arrives `application/x-www-form-urlencoded` with three fields — `Response`, `TransactionIdentifier` and `SpiToken` — and the whole authentication document is nested inside `Response` as a JSON *string*. Reading `$_POST['IsoResponseCode']` finds nothing. `fromCallback()` unwraps it; it also accepts a JSON body or an already-decoded `Response`.

`ThreeDSecureResult` exposes `spiToken`, `isoResponseCode`, `responseMessage`, `authenticationStatus`, `eci`, `cavv`, `protocolVersion`, `dsTransId`, `panToken`, `cardBrand`, `cardholderInfo`, plus `getRaw()` for anything unmodelled. The helpers are `isAuthenticated()` (`Y` or `A`), `isThreeDsUnsupported()` (`3D1`), and `canCompletePayment()`.

If `cardholderInfo` is present, the issuer wants that message shown to the cardholder.

### Reading the response codes

`IsoResponseCode` carries two different families of code depending on the stage:

| Code | Meaning | Where you see it |
|---|---|---|
| `SP4` | SPI preprocessing complete | Step 1 — a redirect is pending |
| `HP0` | HPP preprocessing complete | Step 1, hosted page |
| `3D0` | 3DS complete | POSTed to your `merchantResponseUrl` |
| `3D1` | 3DS not supported by the card | Same; proceed as standard e-commerce |
| `00` | Issuer approved | Step 2, after payment completion |

Prefer the helpers over comparing codes by hand: `requiresRedirect()`, `isApproved()`, `isNonFinancialSuccess()`, `isDeclined()`, `isRetryable()`, `requiresCardRetention()`.

All 92 documented ISO 8583 codes are modelled. `isoResponseCode` is **nullable** — card networks add codes, so it is null for anything unrecognised, and `isoResponseCodeValue` always holds the raw string the gateway sent. Nothing is ever substituted:

```php
if ($payment->isoResponseCode?->isRetryable()) {
    // 91 issuer inoperative, 96 system malfunction, 98 host unreachable…
    // Transient. Retrying is reasonable.
}

// Always safe to log, even for a code the SDK does not know:
error_log("gateway returned {$payment->isoResponseCodeValue}");
```

`isRetryable()` matters operationally: `91` (issuer unreachable) may succeed on a retry, while `05` (do not honour) will not, and retrying it risks tripping issuer velocity rules.

3DS requires `CardholderName` on the source, plus an **email address and/or phone number** on the billing address. Omitting both fails the authentication.

## Hosted Payment Pages

HPP keeps card entry off your servers, reducing your PCI DSS scope. There is no separate HPP endpoint — it is an ordinary `spi/sale` or `spi/auth` carrying hosted-page parameters and **no card source**. The cardholder types their card into the gateway's iframe.

```php
use PowerTranz\Model\Request\Parts\HostedPage;

$result = $client->hostedPage->sale(
    totalAmount:         Money::of('50.00', 'USD'),
    orderIdentifier:     'order-1234',
    page:                HostedPage::fromPortal('MyPageSet', 'MyPageName'),
    merchantResponseUrl: 'https://example.com/payment/return',
);

if ($result instanceof ThreeDSecureChallenge) {
    $_SESSION['spi_token'] = $result->spiToken;
    echo $result->iframe();
    exit;
}
```

From there the flow is identical to 3DS: the cardholder pays in the iframe, PowerTranz posts to your `merchantResponseUrl`, and you complete with `payment()`.

> Page sets created in the Merchant Portal **must** carry a `PTZ/` prefix. Without it the page silently fails to load and the transaction fails with no clear reason. `HostedPage::fromPortal()` adds it for you; use the constructor directly only if your page set genuinely has no prefix.

## Tokenisation

Tokenising happens on **`riskManagement()`** — a non-financial request that moves no money. `Tokenize` is not accepted on `sale` or `authorize`.

```php
$result = $client->spi->riskManagement(new RiskManagementRequest(
    totalAmount:     Money::of('1.00', 'USD'),   // nominal; nothing is charged
    orderIdentifier: 'tokenise-1234',
    source:          $cardSource,
    tokenize:        true,
));

$token = $result->panToken;   // store this, never the PAN
```

`$result->approved` is `false` here — nothing was approved because nothing was charged. Check `panToken` instead, or `isoResponseCode` for `TK0`.

Charge the token later with a `TokenSource`:

```php
use PowerTranz\Model\Request\Parts\TokenSource;

$client->spi->sale(new SaleRequest(
    totalAmount:     Money::of('19.99', 'USD'),
    orderIdentifier: 'order-5678',
    source:          new TokenSource($token),
));
```

Note the field names differ by direction: the gateway **returns** the token as `PanToken` but **expects** it back as `Source.Token`. `TokenSource` handles that. For First Atlantic Commerce tokens use `TokenSource::fac($token)`, which tags them `PG2`.

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

`isoResponseCode`, `responseCode`, `responseMessage`, `transactionIdentifier`, `orderIdentifier`, `referenceNumber`, `authorizationCode`, `panToken`, `spiToken`, `cardBrand`, `transactionType`, `totalAmount`, `approved`, `requiresRedirect`

Every one of those is a **projection** of the gateway response, not a copy of it. Some keys are renamed (`RRN` becomes `referenceNumber`), some values are converted (`totalAmount` becomes a `Money`, normalised to 2dp), and some are computed by the SDK rather than sent at all (`approved`, `requiresRedirect`, `isoResponseCode`'s enum case and `label()`).

When you need the gateway's own words — audit logs, support tickets, debugging an unmodelled field — use the raw accessors, available on `SpiResponse`, `ThreeDSecureChallenge` and `ThreeDSecureResult` alike:

```php
$response->getRaw('FieldName');   // one field, untouched
$response->raw();                 // the whole decoded payload, untouched
```

`raw()` is the safest thing to log: it never renames, coerces, or infers.

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
