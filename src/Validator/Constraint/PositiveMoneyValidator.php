<?php

declare(strict_types=1);

namespace PowerTranz\Validator\Constraint;

use Brick\Money\Money;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates that a {@see \Brick\Money\Money} value is strictly positive.
 *
 * This validator is automatically discovered by Symfony's {@see ConstraintValidatorFactory}
 * because its class name follows the convention: constraint class name + 'Validator'.
 */
final class PositiveMoneyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PositiveMoney) {
            throw new UnexpectedTypeException($constraint, PositiveMoney::class);
        }

        // null is handled by a separate #[Assert\NotNull] if required;
        // here we only validate non-null Money values.
        if ($value === null) {
            return;
        }

        if (!$value instanceof Money) {
            throw new UnexpectedValueException($value, Money::class);
        }

        if (!$value->isPositive()) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
