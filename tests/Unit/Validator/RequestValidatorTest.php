<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Validator;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\CaptureRequest;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\TokenSource;
use PowerTranz\Model\Request\PaymentRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Request\VoidRequest;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tests covering RequestValidator behaviour and integration with request models.
 */
final class RequestValidatorTest extends TestCase
{
    // -----------------------------------------------------------------------
    // RequestValidator::validateValue
    // -----------------------------------------------------------------------

    public function testValidateValuePassesForValidInput(): void
    {
        // Should not throw.
        RequestValidator::validateValue(
            'https://example.com/callback',
            [new Assert\NotBlank(), new Assert\Url()],
            'returnUrl',
        );

        $this->addToAssertionCount(1);
    }

    public function testValidateValueThrowsForBlankString(): void
    {
        $this->expectException(ValidationException::class);

        RequestValidator::validateValue('', new Assert\NotBlank(), 'orderIdentifier');
    }

    public function testValidateValueErrorMapContainsFieldName(): void
    {
        try {
            RequestValidator::validateValue('not-a-url', new Assert\Url(), 'returnUrl');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('returnUrl', $e->getErrors());
        }
    }

    // -----------------------------------------------------------------------
    // CardSource — attribute-based constraint validation
    // -----------------------------------------------------------------------

    public function testCardSourceValidatesSuccessfully(): void
    {
        $source = new CardSource('4111111111111111', '2512', '123', 'Jane Doe');

        self::assertSame('4111111111111111', $source->cardPan);
    }

    public function testCardSourceInvalidPanThrowsValidationException(): void
    {
        try {
            new CardSource('123', '2512', '123', 'Jane Doe');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cardPan', $e->getErrors());
            self::assertStringContainsString('12–19 digits', $e->getErrors()['cardPan']);
        }
    }

    public function testCardSourceMultipleViolationsAreAllCollected(): void
    {
        // Both PAN and CVV are invalid — both errors should appear.
        try {
            new CardSource('123', '2512', '1', 'Jane Doe');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cardPan', $e->getErrors());
            self::assertArrayHasKey('cardCvv', $e->getErrors());
        }
    }

    // -----------------------------------------------------------------------
    // TokenSource
    // -----------------------------------------------------------------------

    public function testTokenSourceEmptyPanTokenThrowsValidationException(): void
    {
        try {
            new TokenSource('');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('panToken', $e->getErrors());
        }
    }

    public function testTokenSourceInvalidCvvThrowsValidationException(): void
    {
        try {
            new TokenSource('valid-pan-token', '12');
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('cardCvv', $e->getErrors());
        }
    }

    public function testTokenSourceNullCvvIsValid(): void
    {
        $source = new TokenSource('valid-pan-token', null);
        self::assertNull($source->cardCvv);
    }

    // -----------------------------------------------------------------------
    // SaleRequest / SpiRequest constraints
    // -----------------------------------------------------------------------

    public function testSaleRequestRejectsZeroAmount(): void
    {
        try {
            new SaleRequest(
                totalAmount:     Money::of('0.00', 'USD'),
                orderIdentifier: 'order-1',
                source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
            );
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('totalAmount', $e->getErrors());
        }
    }

    public function testSaleRequestRejectsBlankOrderIdentifier(): void
    {
        try {
            new SaleRequest(
                totalAmount:     Money::of('10.00', 'USD'),
                orderIdentifier: '   ',
                source:          new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
            );
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('orderIdentifier', $e->getErrors());
        }
    }

    public function testSaleRequestRejectsInvalidUuid(): void
    {
        try {
            new SaleRequest(
                totalAmount:           Money::of('10.00', 'USD'),
                orderIdentifier:       'order-1',
                source:                new CardSource('4111111111111111', '2512', '123', 'Jane Doe'),
                transactionIdentifier: 'not-a-uuid',
            );
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('transactionIdentifier', $e->getErrors());
            self::assertStringContainsString('valid UUID', $e->getErrors()['transactionIdentifier']);
        }
    }

    // -----------------------------------------------------------------------
    // CaptureRequest — cross-field currency callback
    // -----------------------------------------------------------------------

    public function testCaptureRequestRejectsMismatchedTipCurrency(): void
    {
        try {
            new CaptureRequest(
                transactionIdentifier: 'txn-001',
                totalAmount:           Money::of('100.00', 'USD'),
                tipAmount:             Money::of('10.00', 'EUR'),
            );
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('tipAmount', $e->getErrors());
            self::assertStringContainsString('currency must match', $e->getErrors()['tipAmount']);
        }
    }

    public function testCaptureRequestAcceptsMatchingCurrencies(): void
    {
        $request = new CaptureRequest(
            transactionIdentifier: 'txn-001',
            totalAmount:           Money::of('100.00', 'USD'),
            tipAmount:             Money::of('10.00', 'USD'),
            taxAmount:             Money::of('8.50', 'USD'),
        );

        self::assertSame('txn-001', $request->transactionIdentifier);
    }

    // -----------------------------------------------------------------------
    // VoidRequest / PaymentRequest — simple NotBlank
    // -----------------------------------------------------------------------

    public function testVoidRequestRejectsEmptyTransactionIdentifier(): void
    {
        $this->expectException(ValidationException::class);

        new VoidRequest('');
    }

    public function testPaymentRequestRejectsEmptySpiToken(): void
    {
        $this->expectException(ValidationException::class);

        new PaymentRequest('');
    }

    // -----------------------------------------------------------------------
    // RequestValidator static cache reset
    // -----------------------------------------------------------------------

    public function testResetClearsStaticCache(): void
    {
        // Warm the cache by triggering a validation.
        new VoidRequest('txn-001');

        // Reset should not throw and subsequent validations should still work.
        RequestValidator::reset();

        $request = new VoidRequest('txn-002');
        self::assertSame('txn-002', $request->transactionIdentifier);
    }
}
