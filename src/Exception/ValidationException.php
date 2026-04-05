<?php

declare(strict_types=1);

namespace PowerTranz\Exception;

/**
 * Thrown when request parameters fail local validation before any HTTP call is made.
 */
class ValidationException extends PowerTranzException
{
    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string> $errors Field-level validation errors.
     */
    public function __construct(string $message, array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
