<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use PowerTranz\Config\Configuration;
use PowerTranz\Config\ConfigurationBuilder;
use PowerTranz\Config\Environment;

final class ConfigurationTest extends TestCase
{
    public function testConstructsWithRequiredParams(): void
    {
        $config = new Configuration('my-id', 'my-password');

        self::assertSame('my-id', $config->powerTranzId);
        self::assertSame('my-password', $config->powerTranzPassword);
        self::assertSame(Environment::SANDBOX, $config->environment);
        self::assertSame(30, $config->timeout);
        self::assertSame(3, $config->maxRetries);
    }

    public function testBaseUrlComesFromEnvironment(): void
    {
        $sandbox = new Configuration('id', 'pw', Environment::SANDBOX);
        $prod    = new Configuration('id', 'pw', Environment::PRODUCTION);

        self::assertSame('https://staging.ptranz.com/api/', $sandbox->baseUrl());
        self::assertSame('https://api.ptranz.com/api/', $prod->baseUrl());
    }

    public function testThrowsOnEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PowerTranzId/');

        new Configuration('', 'password');
    }

    public function testThrowsOnEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PowerTranzPassword/');

        new Configuration('id', '');
    }

    public function testBuilderProducesValidConfiguration(): void
    {
        $config = ConfigurationBuilder::create()
            ->withCredentials('builder-id', 'builder-pw')
            ->withProductionEnvironment()
            ->withTimeout(60)
            ->withMaxRetries(5)
            ->build();

        self::assertSame('builder-id', $config->powerTranzId);
        self::assertSame(Environment::PRODUCTION, $config->environment);
        self::assertSame(60, $config->timeout);
        self::assertSame(5, $config->maxRetries);
    }

    public function testBuilderIsImmutable(): void
    {
        $base    = ConfigurationBuilder::create()->withCredentials('id', 'pw');
        $sandbox = $base->withSandboxEnvironment();
        $prod    = $base->withProductionEnvironment();

        self::assertSame(Environment::SANDBOX, $sandbox->build()->environment);
        self::assertSame(Environment::PRODUCTION, $prod->build()->environment);
    }
}
