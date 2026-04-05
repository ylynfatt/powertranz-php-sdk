<?php

declare(strict_types=1);

namespace PowerTranz\Config;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Immutable configuration value object for the PowerTranz SDK.
 *
 * Construct directly or via {@see ConfigurationBuilder}.
 */
final class Configuration
{
    public readonly string $powerTranzId;
    public readonly string $powerTranzPassword;
    public readonly Environment $environment;
    public readonly int $timeout;
    public readonly int $connectTimeout;
    public readonly int $maxRetries;
    public readonly float $retryBaseDelay;
    public readonly LoggerInterface $logger;

    public function __construct(
        string $powerTranzId,
        string $powerTranzPassword,
        Environment $environment = Environment::SANDBOX,
        int $timeout = 30,
        int $connectTimeout = 10,
        int $maxRetries = 3,
        float $retryBaseDelay = 0.5,
        LoggerInterface $logger = new NullLogger(),
    ) {
        if (trim($powerTranzId) === '') {
            throw new \InvalidArgumentException('PowerTranz-PowerTranzId must not be empty.');
        }

        if (trim($powerTranzPassword) === '') {
            throw new \InvalidArgumentException('PowerTranz-PowerTranzPassword must not be empty.');
        }

        if ($timeout < 1) {
            throw new \InvalidArgumentException('Timeout must be at least 1 second.');
        }

        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('maxRetries must be 0 or greater.');
        }

        $this->powerTranzId       = $powerTranzId;
        $this->powerTranzPassword = $powerTranzPassword;
        $this->environment        = $environment;
        $this->timeout            = $timeout;
        $this->connectTimeout     = $connectTimeout;
        $this->maxRetries         = $maxRetries;
        $this->retryBaseDelay     = $retryBaseDelay;
        $this->logger             = $logger;
    }

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }
}
