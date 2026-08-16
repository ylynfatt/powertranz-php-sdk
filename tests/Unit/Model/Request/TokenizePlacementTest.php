<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Model\Request;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\RiskManagementRequest;
use PowerTranz\Model\Request\SaleRequest;

/**
 * Tokenize is documented on /spi/RiskMgmt only — not on /spi/Sale or /spi/Auth.
 * These tests pin that placement so it cannot drift back onto the financial
 * endpoints.
 */
final class TokenizePlacementTest extends TestCase
{
    private function cardSource(): CardSource
    {
        return new CardSource('4111111111111111', '2512', '123', 'Jane Doe');
    }

    public function testRiskManagementSendsTokenizeWhenEnabled(): void
    {
        $request = new RiskManagementRequest(
            totalAmount:     Money::of('1.00', 'USD'),
            orderIdentifier: 'tokenise-1',
            source:          $this->cardSource(),
            tokenize:        true,
        );

        $data = $request->jsonSerialize();

        self::assertArrayHasKey('Tokenize', $data);
        self::assertTrue($data['Tokenize']);
    }

    public function testRiskManagementOmitsTokenizeWhenDisabled(): void
    {
        $request = new RiskManagementRequest(
            totalAmount:     Money::of('1.00', 'USD'),
            orderIdentifier: 'tokenise-2',
            source:          $this->cardSource(),
        );

        self::assertArrayNotHasKey('Tokenize', $request->jsonSerialize());
    }

    public function testSaleNeverSendsTokenize(): void
    {
        $request = new SaleRequest(
            totalAmount:     Money::of('10.00', 'USD'),
            orderIdentifier: 'order-1',
            source:          $this->cardSource(),
        );

        self::assertArrayNotHasKey('Tokenize', $request->jsonSerialize());
    }

    public function testAuthNeverSendsTokenize(): void
    {
        $request = new AuthRequest(
            totalAmount:     Money::of('10.00', 'USD'),
            orderIdentifier: 'order-2',
            source:          $this->cardSource(),
        );

        self::assertArrayNotHasKey('Tokenize', $request->jsonSerialize());
    }

    /**
     * Tokenize is not a constructor parameter on the financial requests at all,
     * so passing it is a type error rather than a silently ignored argument.
     */
    public function testSaleHasNoTokenizeParameter(): void
    {
        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionClass(SaleRequest::class))->getConstructor()?->getParameters() ?? [],
        );

        self::assertNotContains('tokenize', $parameters);
    }
}
