<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Config\ConfigurationBuilder;
use PowerTranz\Config\Environment;
use PowerTranz\Http\HttpClientInterface;
use PowerTranz\Http\RetryMiddleware;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\PowerTranzClient;
use PowerTranz\Service\HostedPageService;
use PowerTranz\Service\SpiService;
use PowerTranz\Service\TransactionService;
use PowerTranz\Tests\Fixture\CollectingLogger;
use PowerTranz\Tests\Fixture\MockHttpClient;
use PowerTranz\Tests\Fixture\ResponseFixture;

/**
 * The client is the SDK's front door, and {@see PowerTranzClient::fromConfiguration()}
 * used to discard most of what it was given: it built an HTTP client into a local
 * variable and never passed it, then round-tripped the Configuration through
 * ConfigurationBuilder — losing the logger and retryBaseDelay, which the builder
 * either was not told about or could not express.
 *
 * These tests pin the whole of a Configuration reaching the services.
 */
final class PowerTranzClientTest extends TestCase
{
    public function testConstructsAllThreeServices(): void
    {
        $client = new PowerTranzClient('merchant-id', 'password');

        self::assertInstanceOf(SpiService::class, $client->spi);
        self::assertInstanceOf(TransactionService::class, $client->transactions);
        self::assertInstanceOf(HostedPageService::class, $client->hostedPage);
    }

    /**
     * The defect in full: a logger set on the Configuration never reached the
     * services, so a DI-container integration silently logged nothing.
     *
     * Asserted behaviourally — AbstractService logs through $config->logger, so
     * records arriving in the logger prove the Configuration itself got through.
     */
    public function testFromConfigurationHonoursTheLoggerOnTheConfiguration(): void
    {
        $logger = new CollectingLogger();
        $http   = new MockHttpClient();
        $http->addResponse(200, ResponseFixture::load('sale_approved'));

        $config = new Configuration(
            powerTranzId:       'merchant-id',
            powerTranzPassword: 'password',
            logger:             $logger,
        );

        $client = PowerTranzClient::fromConfiguration($config, $http);
        $client->spi->sale($this->makeSaleRequest());

        self::assertNotSame([], $logger->records, 'The Configuration logger was not used.');
    }

    /**
     * retryBaseDelay has no setter path through the constructor options at all,
     * so a round-trip through ConfigurationBuilder always reset it to the 0.5s
     * default. Read back off the service to prove the object is passed by
     * reference rather than rebuilt.
     */
    public function testFromConfigurationPreservesRetryBaseDelay(): void
    {
        $config = new Configuration(
            powerTranzId:       'merchant-id',
            powerTranzPassword: 'password',
            retryBaseDelay:     2.75,
        );

        $client = PowerTranzClient::fromConfiguration($config);

        self::assertSame($config, $this->configOf($client->spi));
        self::assertSame(2.75, $this->configOf($client->spi)->retryBaseDelay);
    }

    public function testFromConfigurationPassesTheSameConfigurationToEveryService(): void
    {
        $config = ConfigurationBuilder::create()
            ->withCredentials('merchant-id', 'password')
            ->withProductionEnvironment()
            ->withMaxRetries(5)
            ->build();

        $client = PowerTranzClient::fromConfiguration($config);

        self::assertSame($config, $this->configOf($client->spi));
        self::assertSame($config, $this->configOf($client->transactions));
        self::assertSame(Environment::PRODUCTION, $this->configOf($client->spi)->environment);
        self::assertSame(5, $this->configOf($client->spi)->maxRetries);
    }

    /**
     * The $httpClient argument was accepted, assigned, and then dropped —
     * every call went out over a freshly built cURL client instead.
     */
    public function testFromConfigurationUsesTheSuppliedHttpClient(): void
    {
        $http = new MockHttpClient();
        $http->addResponse(200, ResponseFixture::load('sale_approved'));

        $config = new Configuration('merchant-id', 'password');
        $client = PowerTranzClient::fromConfiguration($config, $http);

        $client->spi->sale($this->makeSaleRequest());

        self::assertSame(1, $http->getRequestCount());
        self::assertSame($http, $this->httpClientOf($client->spi));
    }

    /**
     * A supplied SDK client is a complete transport stack, so it must not be
     * wrapped again — doing so would silently double the caller's retry policy.
     */
    public function testSuppliedSdkHttpClientIsNotWrappedInRetryMiddleware(): void
    {
        $http   = new MockHttpClient();
        $config = new Configuration('merchant-id', 'password');

        $client = PowerTranzClient::fromConfiguration($config, $http);

        self::assertNotInstanceOf(RetryMiddleware::class, $this->httpClientOf($client->spi));
    }

    /**
     * With no client supplied, retries are still on by default.
     */
    public function testFromConfigurationDefaultsToRetryingTransport(): void
    {
        $client = PowerTranzClient::fromConfiguration(new Configuration('merchant-id', 'password'));

        self::assertInstanceOf(RetryMiddleware::class, $this->httpClientOf($client->spi));
    }

    public function testConfigurationCanBePassedStraightToTheConstructor(): void
    {
        $config = new Configuration('merchant-id', 'password', retryBaseDelay: 1.5);

        $client = new PowerTranzClient($config);

        self::assertSame($config, $this->configOf($client->spi));
    }

    /**
     * Options that carry configuration would be silently shadowed by the
     * Configuration — the exact failure mode being fixed. Reject them loudly.
     */
    public function testConfigOptionsAlongsideAConfigurationAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be combined with a Configuration/');

        new PowerTranzClient(
            new Configuration('merchant-id', 'password'),
            '',
            ['logger' => new CollectingLogger()],
        );
    }

    public function testPasswordAlongsideAConfigurationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not both/');

        new PowerTranzClient(new Configuration('merchant-id', 'password'), 'password');
    }

    /**
     * Transport options remain valid next to a Configuration — they describe how
     * to talk to the gateway, not what to say.
     */
    public function testTransportOptionsAreAllowedAlongsideAConfiguration(): void
    {
        $http = new MockHttpClient();

        $client = new PowerTranzClient(
            new Configuration('merchant-id', 'password'),
            '',
            ['http_client' => $http],
        );

        self::assertSame($http, $this->httpClientOf($client->spi));
    }

    public function testCredentialOptionsStillReachTheConfiguration(): void
    {
        $logger = new CollectingLogger();

        $client = new PowerTranzClient('merchant-id', 'password', [
            'environment' => Environment::PRODUCTION,
            'timeout'     => 45,
            'max_retries' => 7,
            'logger'      => $logger,
        ]);

        $config = $this->configOf($client->spi);

        self::assertSame(Environment::PRODUCTION, $config->environment);
        self::assertSame(45, $config->timeout);
        self::assertSame(7, $config->maxRetries);
        self::assertSame($logger, $config->logger);
    }

    public function testPsrClientWithoutFactoriesIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/request_factory/');

        new PowerTranzClient('merchant-id', 'password', [
            'http_client' => new class implements \Psr\Http\Client\ClientInterface {
                public function sendRequest(
                    \Psr\Http\Message\RequestInterface $request,
                ): \Psr\Http\Message\ResponseInterface {
                    throw new \LogicException('not called');
                }
            },
        ]);
    }

    private function makeSaleRequest(): SaleRequest
    {
        return new SaleRequest(
            totalAmount:     Money::of('99.50', 'USD'),
            orderIdentifier: 'order-456',
            source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
        );
    }

    /**
     * The services hold these protected, and the point of these tests is that
     * the right instances arrive — which is not otherwise observable.
     */
    private function configOf(object $service): Configuration
    {
        $value = (new \ReflectionProperty($service, 'config'))->getValue($service);
        self::assertInstanceOf(Configuration::class, $value);

        return $value;
    }

    private function httpClientOf(object $service): HttpClientInterface
    {
        $value = (new \ReflectionProperty($service, 'httpClient'))->getValue($service);
        self::assertInstanceOf(HttpClientInterface::class, $value);

        return $value;
    }
}
