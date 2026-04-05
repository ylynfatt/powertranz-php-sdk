<?php

declare(strict_types=1);

namespace PowerTranz\Validator;

use PowerTranz\Exception\ValidationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Thin static façade around the Symfony Validator component.
 *
 * A single shared {@see ValidatorInterface} instance is built lazily with PHP 8
 * attribute mapping enabled and cached for the lifetime of the process.  All
 * request model constructors call {@see self::validate()} to trigger constraint
 * evaluation and surface violations as a {@see ValidationException} whose
 * {@see ValidationException::getErrors()} map is keyed by property path.
 *
 * Two entry-points are provided:
 *
 *  - {@see validate()} — validates an object against its attribute-declared
 *    property constraints (the normal path for request value objects).
 *
 *  - {@see validateValue()} — validates a single scalar value against an
 *    explicit list of constraints (used for ad-hoc service-layer checks,
 *    e.g. validating a URL parameter before building an HPP redirect).
 */
final class RequestValidator
{
    private static ?ValidatorInterface $instance = null;

    /**
     * Validate $object using the Symfony constraints declared on its properties
     * (and any {@see \Symfony\Component\Validator\Constraints\Callback} methods).
     *
     * Violations from parent class properties are included automatically.
     *
     * @throws ValidationException with a field-keyed error map on violation.
     */
    public static function validate(object $object, string $context = 'Validation failed.'): void
    {
        $violations = self::getInstance()->validate($object);

        if (count($violations) === 0) {
            return;
        }

        $errors = [];

        foreach ($violations as $violation) {
            // Property path is already the bare field name for top-level properties
            // (e.g. 'transactionIdentifier', 'totalAmount').  Strip any leading dot
            // that Symfony adds for root-object violations.
            $path          = ltrim((string) $violation->getPropertyPath(), '.');
            $errors[$path] = (string) $violation->getMessage();
        }

        throw new ValidationException($context, $errors);
    }

    /**
     * Validate a single scalar value against the supplied constraints.
     *
     * Useful for validating individual method parameters in services where
     * attribute-based property constraints are not applicable.
     *
     * @param  Constraint|list<Constraint> $constraints
     * @throws ValidationException with a single-field error map on violation.
     */
    public static function validateValue(
        mixed $value,
        Constraint|array $constraints,
        string $field,
        string $context = 'Validation failed.',
    ): void {
        $violations = self::getInstance()->validate($value, $constraints);

        if (count($violations) === 0) {
            return;
        }

        // Only record the first violation for a single-value check.
        $message = (string) $violations->get(0)->getMessage();

        throw new ValidationException($context, [$field => $message]);
    }

    /**
     * Reset the cached validator instance.
     *
     * Primarily useful in test tearDown when the validator cache must be
     * cleared between test suites that swap autoloaders or constraint sets.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function getInstance(): ValidatorInterface
    {
        if (self::$instance === null) {
            self::$instance = Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator();
        }

        return self::$instance;
    }
}
