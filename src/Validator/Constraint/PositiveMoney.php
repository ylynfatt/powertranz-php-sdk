<?php

declare(strict_types=1);

namespace PowerTranz\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Asserts that a {@see \Brick\Money\Money} value is strictly positive (amount > 0).
 *
 * Usage:
 *
 *   #[PositiveMoney(message: 'TotalAmount must be greater than zero.')]
 *   public readonly Money $totalAmount,
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PositiveMoney extends Constraint
{
    public string $message = 'The monetary amount must be greater than zero.';

    public function __construct(
        ?string $message = null,
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options ?? [], $groups, $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }
}
