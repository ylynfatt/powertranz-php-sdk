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
    /**
     * @dataProvider portalPageSets
     */
    public function testFromPortalAppliesPrefixExactlyOnce(string $input): void
    {
        self::assertSame('PTZ/HPPTest', HostedPage::fromPortal($input, 'P')->pageSet);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function portalPageSets(): array
    {
        return [
            'bare'               => ['HPPTest'],
            'already prefixed'   => ['PTZ/HPPTest'],
            'lowercase prefix'   => ['ptz/HPPTest'],
            'mixed-case prefix'  => ['Ptz/HPPTest'],
            'padded'             => ['  HPPTest  '],
            'padded and prefixed' => ['  PTZ/HPPTest  '],
            'padded inside'      => ['PTZ/  HPPTest'],
        ];
    }

    /**
     * Trimming happens in the constructor, so the direct path behaves the same.
     */
    public function testConstructorTrimsBothValues(): void
    {
        $page = new HostedPage('  PTZ/Set  ', "  Page\t");

        self::assertSame('PTZ/Set', $page->pageSet);
        self::assertSame('Page', $page->pageName);
    }

    public function testBlankPageSetIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new HostedPage('  ', 'PageName');
    }

    /**
     * The prefix must not paper over a blank page set: 'PTZ/' . '' is 'PTZ/',
     * which passes NotBlank, so a page set read from missing configuration would
     * otherwise reach the gateway and fail there with no clear reason.
     *
     * @dataProvider blankPageSets
     */
    public function testFromPortalRejectsBlankPageSet(string $blank): void
    {
        try {
            HostedPage::fromPortal($blank, 'PageName');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('pageSet', $e->getErrors());
            self::assertStringContainsString('must not be empty', $e->getErrors()['pageSet']);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blankPageSets(): array
    {
        return [
            'empty'                 => [''],
            'whitespace'            => ['   '],
            'prefix only'           => ['PTZ/'],
            'lowercase prefix only' => ['ptz/'],
            'prefix and whitespace' => ['PTZ/   '],
        ];
    }

    /**
     * With 3DS off the ExtendedData must not carry 3DS parameters: the gateway
     * accepts that combination and skips authentication silently, and SpiRequest
     * now rejects it — so building it here would make the service unusable.
     */
    public function testSaleWithoutThreeDsOmitsThreeDsParameters(): void
    {
        $this->httpClient->addResponse(200, ResponseFixture::load('sale_3ds_redirect'));

        $this->service->sale(
            totalAmount:         Money::of('10.00', 'USD'),
            orderIdentifier:     'order-hpp-5',
            page:                new HostedPage('PTZ/Set', 'Page'),
            merchantResponseUrl: 'https://merchant.example.com/callback',
            threeDSecure:        false,
        );

        $body = json_decode($this->httpClient->getLastRequest()['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertFalse($body['ThreeDSecure']);
        self::assertArrayNotHasKey('ThreeDSecure', $body['ExtendedData']);
        self::assertSame('https://merchant.example.com/callback', $body['ExtendedData']['MerchantResponseUrl']);
    }

    /**
     * Passing parameters while switching 3DS off is contradictory, so it is
     * surfaced rather than silently resolved in one direction or the other.
     */
    public function testThreeDsParametersWithTheFlagOffAreRejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->sale(
            totalAmount:            Money::of('10.00', 'USD'),
            orderIdentifier:        'order-hpp-6',
            page:                   new HostedPage('PTZ/Set', 'Page'),
            merchantResponseUrl:    'https://merchant.example.com/callback',
            threeDSecure:           false,
            threeDSecureParameters: new ThreeDSecure(),
        );
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
