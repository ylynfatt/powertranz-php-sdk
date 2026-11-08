# Changelog

Notable changes, newest first. The format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

While this package is below 1.0.0, breaking changes go in minor bumps.
Composer reads `^0.1.0` as `>=0.1.0 <0.2.0`, so you pick up a new minor
deliberately rather than on a `composer update`.

## [0.2.0] - 2026-11-08

Two install constraints got narrower here. If `composer require` or
`composer update` can't resolve the package any more, check the breaking
changes below.

### Breaking

- Minimum PHP is now 8.2, up from 8.1.

  `brick/money ^0.11.2` needs PHP 8.2, so installing on 8.1 hasn't actually
  worked since that dependency was added. The constraint now matches what
  installs. No code changed.

- `symfony/validator` is now `^6.4.50|^7.4.23`, was `^6.4|^7.0`.

  This picks up the fix for
  [GHSA-4qpf-57w4-m3wc](https://github.com/symfony/symfony/security/advisories/GHSA-4qpf-57w4-m3wc),
  an SSRF filter bypass in the `Ip` constraint's public and private modes. The
  SDK doesn't use `Assert\Ip`, so nothing here was exposed, but the old
  constraint let consumers resolve unpatched versions.

  Symfony 7.0, 7.1, 7.2 and 7.3 are no longer supported. They're all EOL and
  none of them got a fix for this advisory, so you'll need 6.4.50+ or 7.4.23+.

  Symfony 8 isn't supported yet. 8.1.11 has the fix but needs PHP 8.4.1, which
  is above this package's floor.

- `PowerTranzClient::fromConfiguration()` now uses a supplied HTTP client as-is
  instead of wrapping it in `RetryMiddleware`.

  Passing `null` still gives you the built-in cURL client with retries. If you
  pass your own client and want retries, wrap it yourself:

  ```php
  use PowerTranz\Http\RetryMiddleware;

  PowerTranzClient::fromConfiguration($config, new RetryMiddleware($myClient, $config));
  ```

- Passing `environment`, `timeout`, `connect_timeout`, `max_retries` or
  `logger` alongside a `Configuration` now throws `InvalidArgumentException`.
  Set them on the `Configuration` instead. The transport options
  (`http_client`, `request_factory`, `stream_factory`) still work.

### Fixed

- `fromConfiguration()` ignored its `$httpClient` argument and built its own
  cURL client anyway. It also copied four fields off the `Configuration` into a
  new one, which dropped `logger` and reset `retryBaseDelay` to the default.
  Both constructors now share one code path and use the `Configuration`
  directly.

- `phpunit` exited 2 without running any tests. `phpunit.xml` declared a
  testsuite pointing at `tests/Integration`, which doesn't exist, and PHPUnit
  treats that as a fatal config error. Only `phpunit --testsuite Unit` worked.

- The `CollectingLogger` test fixture crashed under `psr/log` 1.x, whose
  `LoggerInterface::log()` is untyped and so incompatible with the fixture's
  typed override. Test-only, no effect on the shipped SDK.

### Added

- `PowerTranzClient` accepts a `Configuration` as its first argument:

  ```php
  $client = new PowerTranzClient($config);
  ```

  The parameter is now `string|Configuration` and the password keeps its
  position, so existing calls are unaffected.

- CI on PHP 8.2, 8.3 and 8.4, plus a leg resolving the lowest dependency
  versions the constraints allow. PHPStan runs at level 8, and again targeting
  8.2.

- Composer scripts: `test`, `stan`, `stan:php82`, `cs`, `cs:fix` and `ci`.

- Tests for `PowerTranzClient`, which had none.

### Changed

- The `dev-main` branch alias is now `0.2.x-dev`, was `0.1.x-dev`.

## [0.1.1] - 2026-08-16

- Zero-pad ISO 4217 numeric currency codes to three digits.
- Fix the `dev-main` branch alias, which claimed `1.x-dev` on a 0.1 API.
- Document installing from Packagist rather than a VCS repository.

## [0.1.0] - 2026-08-16

First release. SPI transactions, 3-D Secure 2.x, tokenisation, Hosted Payment
Pages and recurring billing.

Releases before 0.2.0 are summarised from git history.

[0.2.0]: https://github.com/ylynfatt/powertranz-php-sdk/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/ylynfatt/powertranz-php-sdk/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/ylynfatt/powertranz-php-sdk/releases/tag/v0.1.0
