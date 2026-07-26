<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Service;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ExtendedData;
use PowerTranz\Model\Request\Parts\HostedPage;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\ThreeDSecureChallenge;
use PowerTranz\Service\HostedPageService;
use PowerTranz\Service\SpiService;
use PowerTranz\Tests\Fixture\MockHttpClient;
use PowerTranz\Tests\Fixture\ResponseFixture;

final class HostedPageServiceTest extends TestCase
{
    private MockHttpClient $httpClient;
    private HostedPageService $service;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $config           = new Configuration('test-id', 'test-pw');
        $this->service    = new HostedPageService(new SpiService($this->httpClient, $config));
    }

    /**
     * HPP is an ordinary /spi/sale — not a GET redirect to a separate host.
     */
    public function testSalePostsToSpiSaleEndpoint(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $this->service->sale(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-1',
            page:                HostedPage::fromPortal('HPPTest', 'HPPTest'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
        );

        $request = $this->httpClient->getLastRequest();

        self::assertStringEndsWith('/api/spi/sale', $request['url']);
        self::assertSame(1, $this->httpClient->getRequestCount());
    }

    public function testSaleBodyMatchesDocumentedHostedPagePayload(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $this->service->sale(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-1',
            page:                HostedPage::fromPortal('HPPTest', 'HPPTest'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
            threeDSecureParameters: new ThreeDSecure(
                challengeWindowSize: ThreeDSecure::WINDOW_600x400,
            ),
        );

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($body['ThreeDSecure']);
        self::assertSame(
            [
                'ThreeDSecure' => [
                    'ChallengeWindowSize' => 4,
                    'ChallengeIndicator'  => '01',
                ],
                'HostedPage' => [
                    'PageSet'  => 'PTZ/HPPTest',
                    'PageName' => 'HPPTest',
                ],
                'MerchantResponseUrl' => 'https://merchant.example.com/callback',
            ],
            $body['ExtendedData'],
        );
    }

    /**
     * The cardholder enters card data on the hosted page, so no Source is sent.
     */
    public function testSaleOmitsSourceEntirely(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $this->service->sale(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-1',
            page:                new HostedPage('PTZ/Set', 'Page'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
        );

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('Source', $body);
    }

    public function testSaleReturnsChallengeCarryingTheHostedPageForm(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $result = $this->service->sale(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-1',
            page:                new HostedPage('PTZ/Set', 'Page'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
        );

        self::assertInstanceOf(ThreeDSecureChallenge::class, $result);
        self::assertSame('spi-token-abc123xyz', $result->spiToken);
        self::assertStringContainsString('<iframe srcdoc="', $result->iframe());
    }

    public function testAuthorizePostsToSpiAuthEndpoint(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $this->service->authorize(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-2',
            page:                new HostedPage('PTZ/Set', 'Page'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
        );

        self::assertStringEndsWith('/api/spi/auth', $this->httpClient->getLastRequest()['url']);
    }

    /**
     * Portal-created page sets must carry the PTZ/ prefix or the page fails to
     * load, surfacing only as a failed transaction.
     */
    public function testFromPortalAppliesPrefixExactlyOnce(): void
    {
        self::assertSame('PTZ/HPPTest', HostedPage::fromPortal('HPPTest', 'P')->pageSet);
        self::assertSame('PTZ/HPPTest', HostedPage::fromPortal('PTZ/HPPTest', 'P')->pageSet);
    }

    public function testBlankPageSetIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new HostedPage('  ', 'PageName');
    }

    /**
     * Sending both card data and hosted-page parameters is contradictory.
     */
    public function testSourceAlongsideHostedPageIsRejected(): void
    {
        try {
            new SaleRequest(
                totalAmount:     Money::of('10.00', 'USD'),
                orderIdentifier: 'order-hpp-3',
                source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
                threeDSecure:    true,
                extendedData:    ExtendedData::forHostedPage(
                    merchantResponseUrl: 'https://merchant.example.com/callback',
                    hostedPage:          new HostedPage('PTZ/Set', 'Page'),
                ),
            );
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('source', $e->getErrors());
            self::assertStringContainsString('must not be sent', $e->getErrors()['source']);
        }
    }

    public function testRequestWithNeitherSourceNorHostedPageIsRejected(): void
    {
        try {
            new SaleRequest(
                totalAmount:     Money::of('10.00', 'USD'),
                orderIdentifier: 'order-hpp-4',
            );
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('source', $e->getErrors());
            self::assertStringContainsString('required', $e->getErrors()['source']);
        }
    }
}
