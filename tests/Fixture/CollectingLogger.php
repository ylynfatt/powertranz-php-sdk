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
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
