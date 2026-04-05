<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;

/**
 * Billing or shipping address attached to an SPI request.
 *
 * All fields are optional; include what you have to improve AVS matching
 * and 3DS authentication success rates.
 */
final class Address implements JsonSerializable
{
    public function __construct(
        public readonly ?string $line1        = null,
        public readonly ?string $line2        = null,
        public readonly ?string $city         = null,
        public readonly ?string $state        = null,
        public readonly ?string $postalCode   = null,
        public readonly ?string $countryCode  = null,
        public readonly ?string $emailAddress = null,
        public readonly ?string $phone        = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        $data = [];

        if ($this->line1 !== null) {
            $data['Line1'] = $this->line1;
        }

        if ($this->line2 !== null) {
            $data['Line2'] = $this->line2;
        }

        if ($this->city !== null) {
            $data['City'] = $this->city;
        }

        if ($this->state !== null) {
            $data['State'] = $this->state;
        }

        if ($this->postalCode !== null) {
            $data['PostalCode'] = $this->postalCode;
        }

        if ($this->countryCode !== null) {
            $data['CountryCode'] = $this->countryCode;
        }

        if ($this->emailAddress !== null) {
            $data['EmailAddress'] = $this->emailAddress;
        }

        if ($this->phone !== null) {
            $data['Phone'] = $this->phone;
        }

        return $data;
    }
}
