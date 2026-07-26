<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Billing or shipping address attached to an SPI request.
 *
 * ## 3DS requires some of these
 *
 * For any 3DS authentication request the gateway requires CardholderName (on the
 * {@see CardSource}) together with an email address and/or a phone number — at
 * least one of the two. A 3DS transaction missing both will be rejected, so
 * supply them whenever 3DS is enabled.
 *
 * ## Character restrictions
 *
 * The gateway rejects accented and symbol characters in the name and address
 * lines: they must be ISO 8859 common characters, and in practice only letters,
 * digits, spaces, apostrophes, periods and dashes are safe. PostalCode is
 * stricter still — strictly alphanumeric, with no spaces or dashes, which means
 * Canadian and UK postcodes must have their separators removed.
 *
 * Every field is nullable so partial addresses can be sent, but the constraints
 * below apply to whatever is provided.
 *
 * @see https://developer.powertranz.com/reference/post_spi-sale
 */
final class Address implements JsonSerializable
{
    /** Characters accepted in names. */
    private const NAME_PATTERN = "/^[a-zA-Z0-9\-' ]*$/";

    /** Characters accepted in address lines, city, county and state. */
    private const ADDRESS_PATTERN = "/^[a-zA-Z0-9\-' .]*$/";

    public function __construct(
        #[Assert\Regex(pattern: self::ADDRESS_PATTERN, message: 'Line1 must not contain accents or symbols.')]
        #[Assert\Length(max: 30, maxMessage: 'Line1 must not exceed 30 characters.')]
        public readonly ?string $line1 = null,

        #[Assert\Regex(pattern: self::ADDRESS_PATTERN, message: 'Line2 must not contain accents or symbols.')]
        #[Assert\Length(max: 30, maxMessage: 'Line2 must not exceed 30 characters.')]
        public readonly ?string $line2 = null,

        #[Assert\Regex(pattern: self::ADDRESS_PATTERN, message: 'City must not contain accents or symbols.')]
        #[Assert\Length(max: 25, maxMessage: 'City must not exceed 25 characters.')]
        public readonly ?string $city = null,

        /** Country subdivision code as defined in ISO 3166-2. */
        #[Assert\Regex(pattern: self::ADDRESS_PATTERN, message: 'State must not contain accents or symbols.')]
        #[Assert\Length(max: 25, maxMessage: 'State must not exceed 25 characters.')]
        public readonly ?string $state = null,

        #[Assert\Regex(
            pattern: '/^[a-zA-Z0-9]*$/',
            message: 'PostalCode must be alphanumeric only — remove spaces and dashes.',
        )]
        #[Assert\Length(max: 10, maxMessage: 'PostalCode must not exceed 10 characters.')]
        public readonly ?string $postalCode = null,

        /** Three-digit numeric ISO 3166 country code, e.g. '840' for the US. */
        #[Assert\Length(max: 3, maxMessage: 'CountryCode must not exceed 3 characters.')]
        public readonly ?string $countryCode = null,

        #[Assert\Email(message: 'EmailAddress must be a valid email address.')]
        #[Assert\Length(max: 50, maxMessage: 'EmailAddress must not exceed 50 characters.')]
        public readonly ?string $emailAddress = null,

        /** Digits only, including country code, e.g. '35301176543210'. */
        #[Assert\Regex(
            pattern: '/^[0-9]+$/',
            message: 'PhoneNumber must contain digits only, including the country code.',
        )]
        public readonly ?string $phoneNumber = null,

        #[Assert\Regex(pattern: self::NAME_PATTERN, message: 'FirstName must not contain accents or symbols.')]
        #[Assert\Length(max: 30, maxMessage: 'FirstName must not exceed 30 characters.')]
        public readonly ?string $firstName = null,

        #[Assert\Regex(pattern: self::NAME_PATTERN, message: 'LastName must not contain accents or symbols.')]
        #[Assert\Length(max: 30, maxMessage: 'LastName must not exceed 30 characters.')]
        public readonly ?string $lastName = null,

        #[Assert\Regex(pattern: self::ADDRESS_PATTERN, message: 'County must not contain accents or symbols.')]
        #[Assert\Length(max: 25, maxMessage: 'County must not exceed 25 characters.')]
        public readonly ?string $county = null,

        #[Assert\Regex(pattern: '/^[0-9]+$/', message: 'PhoneNumber2 must contain digits only.')]
        public readonly ?string $phoneNumber2 = null,

        #[Assert\Regex(pattern: '/^[0-9]+$/', message: 'PhoneNumber3 must contain digits only.')]
        public readonly ?string $phoneNumber3 = null,
    ) {
        RequestValidator::validate($this, 'Address validation failed.');
    }

    public function jsonSerialize(): mixed
    {
        $fields = [
            'FirstName'    => $this->firstName,
            'LastName'     => $this->lastName,
            'Line1'        => $this->line1,
            'Line2'        => $this->line2,
            'City'         => $this->city,
            'County'       => $this->county,
            'State'        => $this->state,
            'PostalCode'   => $this->postalCode,
            'CountryCode'  => $this->countryCode,
            'EmailAddress' => $this->emailAddress,
            'PhoneNumber'  => $this->phoneNumber,
            'PhoneNumber2' => $this->phoneNumber2,
            'PhoneNumber3' => $this->phoneNumber3,
        ];

        return array_filter($fields, static fn (?string $value): bool => $value !== null);
    }
}
