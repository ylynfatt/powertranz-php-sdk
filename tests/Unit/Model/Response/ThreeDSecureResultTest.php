<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Model\Response;

use PHPUnit\Framework\TestCase;
use PowerTranz\Enum\IsoResponseCode;
use PowerTranz\Model\Response\ThreeDSecureResult;

/**
 * The gateway POSTs the 3DS result form-encoded as three fields — Response,
 * TransactionIdentifier, SpiToken — with the whole authentication document
 * nested inside Response as a JSON string. These tests pin that shape, observed
 * from a real sandbox transaction.
 */
final class ThreeDSecureResultTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function responseDocument(): array
    {
        return [
            'TransactionType'       => 1,
            'Approved'              => false,
            'TransactionIdentifier' => 'fd306a7b-dc01-42e5-98b3-d62c1ae25977',
            'TotalAmount'           => 22.00,
            'CurrencyCode'          => '840',
            'CardBrand'             => 'Visa',
            'IsoResponseCode'       => '3D0',
            'ResponseMessage'       => '3DS complete',
            'OrderIdentifier'       => 'demo-5783ad65',
            'PanToken'              => 'tok_1glhritqittv5cslkx5',
            'RiskManagement'        => [
                'ThreeDSecure' => [
                    'Eci'                  => '05',
                    'Cavv'                 => 'AJkBCQIGiIYplVGQaQaIAAAAAAA=',
                    'AuthenticationStatus' => 'Y',
                    'ProtocolVersion'      => '2.1.0',
                    'DsTransId'            => '838c2d2b-4def-415a-9046-de899b25bb4f',
                    'ResponseCode'         => '3D0',
                ],
            ],
        ];
    }

    /**
     * The exact shape observed on the wire: three form fields, JSON in Response.
     *
     * @return array<string, string>
     */
    private function formPost(): array
    {
        return [
            'Response'              => json_encode($this->responseDocument(), JSON_THROW_ON_ERROR),
            'TransactionIdentifier' => 'fd306a7b-dc01-42e5-98b3-d62c1ae25977',
            'SpiToken'              => 'spi-token-abc123xyz',
        ];
    }

    public function testParsesTheFormEncodedCallbackWithNestedJson(): void
    {
        $result = ThreeDSecureResult::fromCallback($this->formPost());

        self::assertSame('spi-token-abc123xyz', $result->spiToken);
        self::assertSame('fd306a7b-dc01-42e5-98b3-d62c1ae25977', $result->transactionIdentifier);
        self::assertSame(IsoResponseCode::THREE_DS_COMPLETE, $result->isoResponseCode);
        self::assertSame('3DS complete', $result->responseMessage);
        self::assertSame('demo-5783ad65', $result->orderIdentifier);
        self::assertSame('Visa', $result->cardBrand);
        self::assertSame('tok_1glhritqittv5cslkx5', $result->panToken);
    }

    public function testExtractsTheNestedThreeDSecureBlock(): void
    {
        $result = ThreeDSecureResult::fromCallback($this->formPost());

        self::assertSame('Y', $result->authenticationStatus);
        self::assertSame('05', $result->eci);
        self::assertSame('AJkBCQIGiIYplVGQaQaIAAAAAAA=', $result->cavv);
        self::assertSame('2.1.0', $result->protocolVersion);
        self::assertSame('838c2d2b-4def-415a-9046-de899b25bb4f', $result->dsTransId);
    }

    public function testAuthenticatedStatusDrivesTheHelpers(): void
    {
        $result = ThreeDSecureResult::fromCallback($this->formPost());

        self::assertTrue($result->isAuthenticated());
        self::assertTrue($result->canCompletePayment());
        self::assertFalse($result->isThreeDsUnsupported());
    }

    public function testAttemptedAuthenticationCountsAsAuthenticated(): void
    {
        $doc = $this->responseDocument();
        $doc['RiskManagement']['ThreeDSecure']['AuthenticationStatus'] = 'A';

        $result = ThreeDSecureResult::fromCallback([
            'Response' => json_encode($doc, JSON_THROW_ON_ERROR),
            'SpiToken' => 'tok',
        ]);

        self::assertTrue($result->isAuthenticated());
    }

    public function testFailedAuthenticationCannotCompletePayment(): void
    {
        $doc = $this->responseDocument();
        $doc['RiskManagement']['ThreeDSecure']['AuthenticationStatus'] = 'N';

        $result = ThreeDSecureResult::fromCallback([
            'Response' => json_encode($doc, JSON_THROW_ON_ERROR),
            'SpiToken' => 'tok',
        ]);

        self::assertFalse($result->isAuthenticated());
        self::assertFalse($result->canCompletePayment());
    }

    /**
     * 3D1 means the card has no 3DS2 support. Payment may still proceed.
     */
    public function testThreeDsUnsupportedCanStillComplete(): void
    {
        $result = ThreeDSecureResult::fromCallback([
            'Response' => json_encode([
                'IsoResponseCode' => '3D1',
                'ResponseMessage' => '3DS not supported',
            ], JSON_THROW_ON_ERROR),
            'SpiToken' => 'tok',
        ]);

        self::assertTrue($result->isThreeDsUnsupported());
        self::assertTrue($result->canCompletePayment());
        self::assertFalse($result->isAuthenticated());
    }

    public function testMissingSpiTokenBlocksCompletion(): void
    {
        $result = ThreeDSecureResult::fromCallback([
            'Response' => json_encode($this->responseDocument(), JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('', $result->spiToken);
        self::assertFalse($result->canCompletePayment());
    }

    /**
     * Response arriving already decoded (e.g. from a JSON body) works too.
     */
    public function testAcceptsAnAlreadyDecodedResponse(): void
    {
        $result = ThreeDSecureResult::fromCallback([
            'Response' => $this->responseDocument(),
            'SpiToken' => 'tok',
        ]);

        self::assertSame(IsoResponseCode::THREE_DS_COMPLETE, $result->isoResponseCode);
        self::assertSame('Y', $result->authenticationStatus);
    }

    /**
     * If a flow ever posts the fields flat rather than wrapped, fall back to the
     * outer body rather than returning an object full of nulls.
     */
    public function testFallsBackToFlatFieldsWhenResponseIsAbsent(): void
    {
        $result = ThreeDSecureResult::fromCallback([
            'IsoResponseCode'       => '3D0',
            'ResponseMessage'       => '3DS complete',
            'TransactionIdentifier' => 'tx-1',
            'SpiToken'              => 'tok',
        ]);

        self::assertSame(IsoResponseCode::THREE_DS_COMPLETE, $result->isoResponseCode);
        self::assertSame('tx-1', $result->transactionIdentifier);
        self::assertSame('tok', $result->spiToken);
    }

    public function testUnmodelledFieldsRemainReachable(): void
    {
        $result = ThreeDSecureResult::fromCallback($this->formPost());

        self::assertSame(1, $result->getRaw('TransactionType'));
        // 22.00 round-trips through JSON as the integer 22.
        self::assertEquals(22, $result->getRaw('TotalAmount'));
        self::assertNull($result->getRaw('NoSuchField'));
    }

    public function testGarbageResponseDoesNotThrow(): void
    {
        $result = ThreeDSecureResult::fromCallback(['Response' => 'not json', 'SpiToken' => 'tok']);

        self::assertNull($result->isoResponseCode);
        self::assertSame('', $result->responseMessage);
        self::assertFalse($result->canCompletePayment());
    }
}
