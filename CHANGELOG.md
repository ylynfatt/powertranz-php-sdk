# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

While the version is below 1.0.0, a minor bump (`0.1.x` → `0.2.0`) is where
breaking changes land. Composer's `^0.1.0` resolves `>=0.1.0 <0.2.0`, so each
minor is an opt-in upgrade rather than something a `composer update` picks up
on its own.

## [0.2.0] — 2026-11-08

This release narrows two install requirements. If `composer require` or
`composer update` now fails to resolve, see **Breaking changes** below — it is
expected, and the reason is in each entry.

### Breaking changes

- **Minimum PHP is now 8.2** (was `>=8.1`).

  The `>=8.1` constraint was never truthful: `brick/money ^0.11.2` requires
  `^8.2`, so the package has been uninstallable on 8.1 since that dependency
  was adopted. The manifest now states what is actually installable. No code
  change was needed; nobody could have been running this on 8.1.

- **`symfony/validator` narrowed to `^6.4.50|^7.4.23`** (was `^6.4|^7.0`).

  Clears [GHSA-4qpf-57w4-m3wc](https://github.com/symfony/symfony/security/advisories/GHSA-4qpf-57w4-m3wc),
  an SSRF filter bypass in the `Ip` constraint's public/private modes. This
  SDK does not use `Assert\Ip`, so there was no exposure here — the problem was
  the constraint handed to consumers, which permitted versions with no fix.

  **This drops support for Symfony 7.0, 7.1, 7.2 and 7.3.** All four are EOL
  and none received a patch for this advisory, so there is no version of them
  that is both supported and safe. Applications on those minors must move to
  6.4.50+ or 7.4.23+.

  Symfony 8 is not yet supported: `8.1.11` carries the fix but requires
  PHP `>=8.4.1`, above this package's floor. It needs its own compatibility
  testing.

- **`PowerTranzClient::fromConfiguration()` no longer wraps a supplied HTTP
  client in `RetryMiddleware`.**

  A client passed as the second argument is now treated as a complete transport
  stack and used verbatim, because wrapping a stack that may already retry
  would silently double a retry policy against a payment gateway. Passing
  `null` still gives you the built-in cURL client with retries.

  Previously the argument was ignored entirely (see Fixed), so callers who
  passed a client were getting retries by accident. To keep that behaviour,
  wrap it yourself:

  ```php
  use PowerTranz\Http\RetryMiddleware;

  PowerTranzClient::fromConfiguration($config, new RetryMiddleware($myClient, $config));
  ```

- **Configuration-bearing constructor options are rejected when a
  `Configuration` is also supplied.**

  `environment`, `timeout`, `connect_timeout`, `max_retries` and `logger` now
  throw `InvalidArgumentException` if combined with a `Configuration`, rather
  than being silently shadowed by it. Set them on the `Configuration` instead.
  Transport options (`http_client`, `request_factory`, `stream_factory`) are
  unaffected.

### Fixed

- **`fromConfiguration()` discarded most of what it was given.** The
  `$httpClient` argument was assigned to a local variable and never passed on,
  so a caller-supplied transport was ignored and every request went out over a
  freshly built cURL client. The `Configuration` was round-tripped through
  `ConfigurationBuilder` by copying four fields across, which dropped
  `$config->logger` (so DI-container integrations logged nothing) and reset
  `$config->retryBaseDelay` to its default. Both entry points now converge on
  one wiring path that uses the `Configuration` by reference.

- **`phpunit` exited 2 without running a single test.** `phpunit.xml` declared
  an `Integration` testsuite pointing at a directory that does not exist, which
  PHPUnit treats as a fatal configuration error. Only
  `phpunit --testsuite Unit` worked.

- **`CollectingLogger` test fixture fatalled under `psr/log` 1.x.** Its typed
  `log()` override is an incompatible signature against 1.x's untyped
  interface. Widened to the signature valid across all three majors. Test-only;
  no effect on the shipped SDK.

### Added

- **`PowerTranzClient` accepts a `Configuration` directly.** The first
  constructor parameter is now `string|Configuration`:

  ```php
  $client = new PowerTranzClient($config);
  ```

  Backward compatible — the parameter is widened, and `$powerTranzPassword`
  keeps its position.

- **CI across PHP 8.2, 8.3 and 8.4**, with an additional leg resolving the
  lowest dependency versions the constraints allow, plus PHPStan at level 8 and
  a pass that re-analyses the source as PHP 8.2. The lowest-dependency leg is
  what surfaced both the `psr/log` fixture fault and the impossible 8.1 floor.

- **Composer scripts** so local commands and CI steps are the same thing:
  `composer test`, `composer stan`, `composer stan:php82`, `composer cs`,
  `composer cs:fix`, and `composer ci` for everything bar the version matrix.

- **Test coverage for `PowerTranzClient`**, which previously had none — the
  reason the `fromConfiguration()` defects went unnoticed.

### Changed

- `branch-alias` for `dev-main` is now `0.2.x-dev`, matching the released line.
  Left at `0.1.x-dev` it would advertise `dev-main` as satisfying `^0.1` while
  actually carrying the 0.2 API.

## [0.1.1] — 2026-08-16

### Fixed

- ISO 4217 numeric currency codes are zero-padded to three digits, so codes
  below 100 serialise correctly.
- `branch-alias` for `dev-main` corrected from `1.x-dev` to `0.1.x-dev`. As
  `1.x-dev` it could satisfy a `^1.0` requirement with a package whose actual
  API was 0.1.

### Changed

- Installation documented via Packagist; the VCS `repositories` block is no
  longer needed.

## [0.1.0] — 2026-08-16

Initial release. SPI transactions, 3-D Secure 2.x, tokenisation, Hosted Payment
Pages, and recurring billing.

Releases before 0.2.0 predate this changelog and are summarised from their
commit history rather than itemised in full.

[0.2.0]: https://github.com/ylynfatt/powertranz-php-sdk/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/ylynfatt/powertranz-php-sdk/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/ylynfatt/powertranz-php-sdk/releases/tag/v0.1.0
