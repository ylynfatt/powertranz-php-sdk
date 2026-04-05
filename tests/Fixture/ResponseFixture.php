<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Fixture;

/**
 * Helper for loading JSON response fixtures in tests.
 */
final class ResponseFixture
{
    public static function load(string $name): string
    {
        $path = __DIR__ . '/Responses/' . $name . '.json';

        if (!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('Fixture "%s" not found at %s.', $name, $path));
        }

        return (string) file_get_contents($path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadAsArray(string $name): array
    {
        return json_decode(self::load($name), true, 512, JSON_THROW_ON_ERROR);
    }
}
