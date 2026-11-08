<?php

declare(strict_types=1);

namespace PowerTranz;

use PowerTranz\Config\Configuration;
use PowerTranz\Config\ConfigurationBuilder;
use PowerTranz\Config\Environment;
use PowerTranz\Http\CurlHttpClient;
use PowerTranz\Http\HttpClientInterface;
use PowerTranz\Http\PsrHttpClient;
use PowerTranz\Http\RetryMiddleware;
use PowerTranz\Service\HostedPageService;
use PowerTranz\Service\SpiService;
use PowerTranz\Service\TransactionService;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Main entry point for the PowerTranz PHP SDK.
 *
 * ## Quick start (cURL, no extra dependencies)
 *
 *   $client = new PowerTranzClient('merchant-id', 'password');
 *
 * ## With environment and logger
 *
 *   $client = new PowerTranzClient('merchant-id', 'password', [
 *       'environment' => Environment::PRODUCTION,
 *       'logger'      => $psrLogger,
 *   ]);
 *
 * ## With a custom PSR-18 HTTP client (e.g. Guzzle + nyholm/psr7)
 *
 *   $psr7   = new \Nyholm\Psr7\Factory\Psr17Factory();
 *   $client = new PowerTranzClient('merchant-id', 'password', [
 *       'http_client'     => new \GuzzleHttp\Client(),
 *       'request_factory' => $psr7,
 *       'stream_factory'  => $psr7,
 *   ]);
 *
 * ## With a fully-configured Configuration object (DI containers)
 *
 *   $client = PowerTranzClient::fromConfiguration($config);
 *
 * The Configuration is used exactly as given — every field on it, including
 * {@see Configuration::$logger} and {@see Configuration::$retryBaseDelay},
 * reaches the services. It is not round-tripped through
 * {@see ConfigurationBuilder}.
 *
 * ## Service accessors
 *
 *   $client->spi->sale($saleRequest);
 *   $client->transactions->capture($captureRequest);
 *   $client->hostedPage->sale(...);
 */
final class PowerTranzClient
{
    public readonly SpiService $spi;
    public readonly TransactionService $transactions;
    public readonly HostedPageService $hostedPage;

    /**
     * Options that carry configuration, and so are meaningless when a
     * pre-built {@see Configuration} is supplied instead of credentials.
     */
    private const CONFIG_OPTIONS = ['environment', 'timeout', 'connect_timeout', 'max_retries', 'logger'];

    /**
     * @param string|Configuration $powerTranzId Merchant id, or a pre-built
     *        {@see Configuration} (in which case $powerTranzPassword must be omitted).
     * @param array{
     *   environment?:      Environment,
     *   timeout?:          int,
     *   connect_timeout?:  int,
     *   max_retries?:      int,
     *   logger?:           LoggerInterface,
     *   http_client?:      PsrClientInterface|HttpClientInterface,
     *   request_factory?:  RequestFactoryInterface,
     *   stream_factory?:   StreamFactoryInterface,
     * } $options
     */
    public function __construct(
        string|Configuration $powerTranzId,
        string $powerTranzPassword = '',
        array $options = [],
    ) {
        $config = $powerTranzId instanceof Configuration
            ? self::adoptConfiguration($powerTranzId, $powerTranzPassword, $options)
            : self::buildConfiguration($powerTranzId, $powerTranzPassword, $options);

        $httpClient = self::resolveHttpClient($config, $options);

        $this->spi          = new SpiService($httpClient, $config);
        $this->transactions = new TransactionService($httpClient, $config);
        $this->hostedPage   = new HostedPageService($this->spi);
    }

    /**
     * Construct from a pre-built {@see Configuration} object.
     *
     * Useful in DI containers (Laravel service providers, Symfony DI, Drupal services).
     *
     * The Configuration is passed through untouched, so fields the
     * {@see ConfigurationBuilder} cannot express — notably
     * {@see Configuration::$retryBaseDelay} — are honoured.
     *
     * @param HttpClientInterface|null $httpClient A complete transport stack, used verbatim.
     *        Pass null for the built-in cURL client wrapped in {@see RetryMiddleware}.
     *        A client supplied here is NOT wrapped: retry behaviour is yours to compose.
     */
    public static function fromConfiguration(
        Configuration $config,
        ?HttpClientInterface $httpClient = null,
    ): self {
        return new self(
            $config,
            '',
            $httpClient !== null ? ['http_client' => $httpClient] : [],
        );
    }

    /**
     * Use a caller-supplied Configuration as-is, rejecting options it would silently shadow.
     *
     * @param array<string, mixed> $options
     */
    private static function adoptConfiguration(
        Configuration $config,
        string $powerTranzPassword,
        array $options,
    ): Configuration {
        if ($powerTranzPassword !== '') {
            throw new \InvalidArgumentException(
                'Pass credentials either as a Configuration or as id/password, not both.'
            );
        }

        $shadowed = array_intersect(self::CONFIG_OPTIONS, array_keys($options));

        if ($shadowed !== []) {
            throw new \InvalidArgumentException(sprintf(
                'These options cannot be combined with a Configuration, which already carries them: %s. '
                . 'Set them on the Configuration instead.',
                implode(', ', $shadowed),
            ));
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function buildConfiguration(
        string $powerTranzId,
        string $powerTranzPassword,
        array $options,
    ): Configuration {
        $builder = ConfigurationBuilder::create()
            ->withCredentials($powerTranzId, $powerTranzPassword);

        if (isset($options['environment'])) {
            $builder = $builder->withEnvironment($options['environment']);
        }

        if (isset($options['timeout'])) {
            $builder = $builder->withTimeout($options['timeout']);
        }

        if (isset($options['connect_timeout'])) {
            $builder = $builder->withConnectTimeout($options['connect_timeout']);
        }

        if (isset($options['max_retries'])) {
            $builder = $builder->withMaxRetries($options['max_retries']);
        }

        if (isset($options['logger'])) {
            $builder = $builder->withLogger($options['logger']);
        }

        return $builder->build();
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function resolveHttpClient(Configuration $config, array $options): HttpClientInterface
    {
        $psrClient = $options['http_client'] ?? null;

        // An SDK-level client is a complete transport stack — honour it verbatim
        // rather than wrapping it and silently doubling someone's retry policy.
        if ($psrClient instanceof HttpClientInterface) {
            return $psrClient;
        }

        if ($psrClient instanceof PsrClientInterface) {
            $requestFactory = $options['request_factory'] ?? null;
            $streamFactory  = $options['stream_factory'] ?? null;

            if (!$requestFactory instanceof RequestFactoryInterface) {
                throw new \InvalidArgumentException(
                    'When providing a PSR-18 http_client, you must also provide a request_factory (RequestFactoryInterface).'
                );
            }

            if (!$streamFactory instanceof StreamFactoryInterface) {
                throw new \InvalidArgumentException(
                    'When providing a PSR-18 http_client, you must also provide a stream_factory (StreamFactoryInterface).'
                );
            }

            $inner = new PsrHttpClient($psrClient, $requestFactory, $streamFactory);
        } else {
            $inner = new CurlHttpClient($config);
        }

        return new RetryMiddleware($inner, $config);
    }
}
