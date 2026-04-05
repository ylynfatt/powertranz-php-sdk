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
 * ## Service accessors
 *
 *   $client->spi->sale($saleRequest);
 *   $client->transactions->capture($captureRequest);
 *   $client->hostedPage->buildRedirectUrl(...);
 */
final class PowerTranzClient
{
    public readonly SpiService $spi;
    public readonly TransactionService $transactions;
    public readonly HostedPageService $hostedPage;

    /**
     * @param array{
     *   environment?:      Environment,
     *   timeout?:          int,
     *   connect_timeout?:  int,
     *   max_retries?:      int,
     *   logger?:           LoggerInterface,
     *   http_client?:      PsrClientInterface,
     *   request_factory?:  RequestFactoryInterface,
     *   stream_factory?:   StreamFactoryInterface,
     * } $options
     */
    public function __construct(
        string $powerTranzId,
        string $powerTranzPassword,
        array $options = [],
    ) {
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

        $config     = $builder->build();
        $httpClient = self::resolveHttpClient($config, $options);

        $this->spi          = new SpiService($httpClient, $config);
        $this->transactions = new TransactionService($httpClient, $config);
        $this->hostedPage   = new HostedPageService($config);
    }

    /**
     * Construct from a pre-built {@see Configuration} object.
     *
     * Useful in DI containers (Laravel service providers, Symfony DI, Drupal services).
     *
     * @param HttpClientInterface|null $httpClient Provide a custom HTTP client, or pass null
     *                                             to use the built-in cURL client.
     */
    public static function fromConfiguration(
        Configuration $config,
        ?HttpClientInterface $httpClient = null,
    ): self {
        if ($httpClient === null) {
            $httpClient = new RetryMiddleware(new CurlHttpClient($config), $config);
        }

        return new self(
            $config->powerTranzId,
            $config->powerTranzPassword,
            // Pass the pre-built http client via options so the constructor uses it directly
            // by re-constructing configuration from the already-built config values.
            // Note: timeout/retries come from $config; the constructor will re-build them.
            [
                'environment'    => $config->environment,
                'timeout'        => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
                'max_retries'    => $config->maxRetries,
            ],
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function resolveHttpClient(Configuration $config, array $options): HttpClientInterface
    {
        $psrClient = $options['http_client'] ?? null;

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
