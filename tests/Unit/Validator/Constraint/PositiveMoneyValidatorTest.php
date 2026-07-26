<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Validator\Constraint;

use Brick\Money\Money;
use PHPUnit\Framework\TestCase;
use PowerTranz\Validator\Constraint\PositiveMoney;
use PowerTranz\Validator\Constraint\PositiveMoneyValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;

final class PositiveMoneyValidatorTest extends TestCase
{
    public function testPositiveMoneyPassesValidation(): void
    {
        $validator  = Validation::createValidator();
        $violations = $validator->validate(Money::of('10.00', 'USD'), new PositiveMoney());

        self::assertCount(0, $violations);
    }

    public function testZeroMoneyFailsValidation(): void
    {
        $validator  = Validation::createValidator();
        $violations = $validator->validate(Money::of('0.00', 'USD'), new PositiveMoney());

        self::assertCount(1, $violations);
        self::assertStringContainsString('greater than zero', (string) $violations->get(0)->getMessage());
    }

    public function testNegativeMoneyFailsValidation(): void
    {
        $validator  = Validation::createValidator();
        $violations = $validator->validate(Money::of('-5.00', 'USD'), new PositiveMoney());

        self::assertCount(1, $violations);
    }

    public function testNullPassesValidation(): void
    {
        // null is valid — use NotNull separately if required.
        $validator  = Validation::createValidator();
        $violations = $validator->validate(null, new PositiveMoney());

        self::assertCount(0, $violations);
    }

    public function testCustomMessageIsUsed(): void
    {
        $validator  = Validation::createValidator();
        $violations = $validator->validate(
            Money::of('0.00', 'USD'),
            new PositiveMoney(message: 'Custom error message.')
        );

        self::assertSame('Custom error message.', (string) $violations->get(0)->getMessage());
    }

    public function testNonMoneyValueThrowsUnexpectedValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $constraint = new PositiveMoney();
        $validator  = new PositiveMoneyValidator();

        // Wire up a minimal execution context
        $context = $this->createMock(ExecutionContextInterface::class);
        $validator->initialize($context);
        $validator->validate('not-a-money-object', $constraint);
    }

    public function testWrongConstraintThrowsUnexpectedTypeException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $validator = new PositiveMoneyValidator();
        $context   = $this->createMock(ExecutionContextInterface::class);
        $validator->initialize($context);

        // Pass a different constraint type — should throw immediately.
        $validator->validate(Money::of('1.00', 'USD'), new \Symfony\Component\Validator\Constraints\NotNull());
    }
}
