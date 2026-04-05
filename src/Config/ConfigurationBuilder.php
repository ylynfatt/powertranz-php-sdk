<?php

declare(strict_types=1);

namespace PowerTranz\Config;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fluent builder for {@see Configuration}.
 *
 * Each setter returns a cloned instance to preserve immutability during construction.
 *
 * Example:
 *   $config = ConfigurationBuilder::create()
 *       ->withCredentials('merchant-id', 'password')
 *       ->withEnvironment(Environment::PRODUCTION)
 *       ->withLogger($myLogger)
 *       ->build();
 */
final class ConfigurationBuilder
{
    private string $powerTranzId       = '';
    private string $powerTranzPassword = '';
    private Environment $environment   = Environment::SANDBOX;
    private int $timeout               = 30;
    private int $connectTimeout        = 10;
    private int $maxRetries            = 3;
    private float $retryBaseDelay      = 0.5;
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public static function create(): self
    {
        return new self();
    }

    public function withCredentials(string $powerTranzId, string $powerTranzPassword): self
    {
        $clone                     = clone $this;
        $clone->powerTranzId       = $powerTranzId;
        $clone->powerTranzPassword = $powerTranzPassword;

        return $clone;
    }

    public function withEnvironment(Environment $environment): self
    {
        $clone              = clone $this;
        $clone->environment = $environment;

        return $clone;
    }

    public function withSandboxEnvironment(): self
    {
        return $this->withEnvironment(Environment::SANDBOX);
    }

    public function withProductionEnvironment(): self
    {
        return $this->withEnvironment(Environment::PRODUCTION);
    }

    public function withTimeout(int $seconds): self
    {
        $clone          = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    public function withConnectTimeout(int $seconds): self
    {
        $clone                 = clone $this;
        $clone->connectTimeout = $seconds;

        return $clone;
    }

    public function withMaxRetries(int $retries): self
    {
        $clone             = clone $this;
        $clone->maxRetries = $retries;

        return $clone;
    }

    public function withRetryBaseDelay(float $seconds): self
    {
        $clone                  = clone $this;
        $clone->retryBaseDelay  = $seconds;

        return $clone;
    }

    public function withLogger(LoggerInterface $logger): self
    {
        $clone         = clone $this;
        $clone->logger = $logger;

        return $clone;
    }

    public function build(): Configuration
    {
        return new Configuration(
            powerTranzId:       $this->powerTranzId,
            powerTranzPassword: $this->powerTranzPassword,
            environment:        $this->environment,
            timeout:            $this->timeout,
            connectTimeout:     $this->connectTimeout,
            maxRetries:         $this->maxRetries,
            retryBaseDelay:     $this->retryBaseDelay,
            logger:             $this->logger,
        );
    }
}
