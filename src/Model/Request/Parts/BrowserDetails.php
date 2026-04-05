<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;

/**
 * Browser environment details required for 3DS 2.x authentication.
 *
 * Typically populated from JavaScript collected on the merchant's checkout page
 * and passed to the server via a form field or AJAX.
 *
 * Use {@see self::fromArray()} to hydrate from a JSON-decoded client payload.
 */
final class BrowserDetails implements JsonSerializable
{
    public function __construct(
        public readonly string $acceptHeader,
        public readonly string $colorDepth,
        public readonly bool $javaEnabled,
        public readonly string $language,
        public readonly int $screenHeight,
        public readonly int $screenWidth,
        public readonly string $timeZone,
        public readonly string $userAgent,
        public readonly bool $javaScriptEnabled = true,
    ) {
    }

    /**
     * Hydrate from a validated array (e.g. from a JSON-decoded client payload).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            acceptHeader:      (string) ($data['acceptHeader'] ?? $data['AcceptHeader'] ?? '*/*'),
            colorDepth:        (string) ($data['colorDepth'] ?? $data['ColorDepth'] ?? '24'),
            javaEnabled:       (bool)   ($data['javaEnabled'] ?? $data['JavaEnabled'] ?? false),
            language:          (string) ($data['language'] ?? $data['Language'] ?? 'en-US'),
            screenHeight:      (int)    ($data['screenHeight'] ?? $data['ScreenHeight'] ?? 0),
            screenWidth:       (int)    ($data['screenWidth'] ?? $data['ScreenWidth'] ?? 0),
            timeZone:          (string) ($data['timeZone'] ?? $data['TimeZone'] ?? '0'),
            userAgent:         (string) ($data['userAgent'] ?? $data['UserAgent'] ?? ''),
            javaScriptEnabled: (bool)   ($data['javaScriptEnabled'] ?? $data['JavaScriptEnabled'] ?? true),
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'AcceptHeader'      => $this->acceptHeader,
            'ColorDepth'        => $this->colorDepth,
            'JavaEnabled'       => $this->javaEnabled,
            'Language'          => $this->language,
            'ScreenHeight'      => $this->screenHeight,
            'ScreenWidth'       => $this->screenWidth,
            'TimeZone'          => $this->timeZone,
            'UserAgent'         => $this->userAgent,
            'JavaScriptEnabled' => $this->javaScriptEnabled,
        ];
    }
}
