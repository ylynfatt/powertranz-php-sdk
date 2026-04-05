<?php

declare(strict_types=1);

namespace PowerTranz\Enum;

use Brick\Money\Money;

/**
 * ISO 4217 numeric currency codes.
 *
 * Caribbean currencies are listed first as PowerTranz is a Caribbean-focused gateway.
 * Case names match ISO 4217 alpha-3 codes exactly, so {@see isoAlpha()} can return
 * {@see CurrencyCode::name} and {@see fromAlphaCode()} can do a reverse lookup.
 */
enum CurrencyCode: int
{
    // Caribbean
    case XCD = 951;  // Eastern Caribbean Dollar (Antigua, Dominica, Grenada, St Kitts, St Lucia, St Vincent)
    case TTD = 780;  // Trinidad and Tobago Dollar
    case JMD = 388;  // Jamaican Dollar
    case BBD = 52;   // Barbadian Dollar
    case BSD = 44;   // Bahamian Dollar
    case BZD = 84;   // Belize Dollar
    case GYD = 328;  // Guyanese Dollar
    case HTG = 332;  // Haitian Gourde
    case KYD = 136;  // Cayman Islands Dollar
    case SRD = 968;  // Surinamese Dollar

    // Major international
    case USD = 840;
    case EUR = 978;
    case GBP = 826;
    case CAD = 124;
    case AUD = 36;
    case NZD = 554;
    case JPY = 392;
    case CHF = 756;
    case HKD = 344;
    case SGD = 702;
    case MXN = 484;
    case BRL = 986;

    /**
     * Returns the zero-padded three-digit numeric string required by the API.
     */
    public function numericString(): string
    {
        return (string) $this->value;
    }

    /**
     * Returns the ISO 4217 alpha-3 code (e.g. 'USD', 'XCD').
     *
     * Enum case names are ISO alpha codes, so this simply returns {@see CurrencyCode::name}.
     */
    public function isoAlpha(): string
    {
        return $this->name;
    }

    /**
     * Create a {@see Money} instance for this currency with the given amount.
     *
     * @param  numeric-string|int|float $amount  Decimal amount, e.g. '29.99'.
     *                                           Pass as a string to avoid float precision issues.
     */
    public function money(int|float|string $amount): Money
    {
        return Money::of((string) $amount, $this->name);
    }

    /**
     * Look up a CurrencyCode by its ISO 4217 alpha-3 code (e.g. 'USD').
     *
     * @throws \ValueError When the supplied alpha code is not in the enum.
     */
    public static function fromAlphaCode(string $alpha): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === strtoupper($alpha)) {
                return $case;
            }
        }

        throw new \ValueError("Currency alpha code '{$alpha}' is not supported by CurrencyCode.");
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD, self::CAD, self::AUD, self::NZD,
            self::BBD, self::BSD, self::BZD, self::GYD,
            self::JMD, self::KYD, self::SRD, self::TTD => '$',
            self::XCD => 'EC$',
            self::EUR => '€',
            self::GBP => '£',
            self::JPY => '¥',
            self::CHF => 'Fr',
            self::HKD => 'HK$',
            self::SGD => 'S$',
            self::MXN => 'MX$',
            self::BRL => 'R$',
            self::HTG => 'G',
        };
    }
}
