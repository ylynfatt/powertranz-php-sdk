<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Fixture;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps everything in memory, so tests can assert on what the
 * SDK logs — in particular that card data and tokens never reach the log.
 */
final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * Deliberately untyped parameters.
     *
     * composer.json allows psr/log ^1.1|^2.0|^3.0, and 1.x declares
     * `log($level, $message, array $context = [])` with no parameter types. A
     * typed override is an incompatible signature there and fatals on load, so
     * the widest signature is the only one valid across all three majors.
     *
     * @param mixed                   $level
     * @param string|\Stringable      $message
     * @param array<mixed>            $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
