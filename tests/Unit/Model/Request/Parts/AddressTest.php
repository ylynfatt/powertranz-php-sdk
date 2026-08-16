<?php

declare(strict_types=1);

namespace PowerTranz\Tests\Unit\Model\Request\Parts;

use PHPUnit\Framework\TestCase;
use PowerTranz\Exception\ValidationException;
use PowerTranz\Model\Request\Parts\Address;

final class AddressTest extends TestCase
{
    /**
     * The gateway field is PhoneNumber. The SDK previously emitted Phone, which
     * the gateway ignores — and 3DS requires an email address and/or a phone
     * number, so the omission broke a mandatory field.
     */
    public function testPhoneSerializesAsPhoneNumber(): void
    {
        $address = new Address(phoneNumber: '18685551234');

        $data = $address->jsonSerialize();

        self::assertSame(['PhoneNumber' => '18685551234'], $data);
        self::assertArrayNotHasKey('Phone', $data);
    }

    public function testFullAddressMatchesDocumentedFieldNames(): void
    {
        $address = new Address(
            line1:        '1200 Whitewall Blvd.',
            line2:        'Unit 15',
            city:         'Boston',
            state:        'NY',
            postalCode:   '200341',
            countryCode:  '840',
            emailAddress: 'john.smith@example.com',
            phoneNumber:  '2113456790',
            firstName:    'John',
            lastName:     'Smith',
        );

        self::assertSame(
            [
                'FirstName'    => 'John',
                'LastName'     => 'Smith',
                'Line1'        => '1200 Whitewall Blvd.',
                'Line2'        => 'Unit 15',
                'City'         => 'Boston',
                'State'        => 'NY',
                'PostalCode'   => '200341',
                'CountryCode'  => '840',
                'EmailAddress' => 'john.smith@example.com',
                'PhoneNumber'  => '2113456790',
            ],
            $address->jsonSerialize(),
        );
    }

    public function testNullFieldsAreOmitted(): void
    {
        self::assertSame([], (new Address())->jsonSerialize());
    }

    /**
     * PostalCode is strictly alphanumeric — no spaces or dashes — so Canadian
     * and UK postcodes must have separators stripped before sending.
     */
    public function testPostalCodeRejectsSpacesAndDashes(): void
    {
        foreach (['M5V 3A8', 'SW1A-1AA'] as $invalid) {
            try {
                new Address(postalCode: $invalid);
                self::fail("Expected ValidationException for postal code {$invalid}");
            } catch (ValidationException $e) {
                self::assertArrayHasKey('postalCode', $e->getErrors());
            }
        }

        // Same values with separators removed are accepted.
        self::assertSame('M5V3A8', (new Address(postalCode: 'M5V3A8'))->postalCode);
    }

    public function testAccentedCharactersAreRejectedInAddressLines(): void
    {
        $this->expectException(ValidationException::class);

        new Address(line1: 'Rue de la Paix é');
    }

    public function testPhoneNumberMustBeDigitsOnly(): void
    {
        try {
            new Address(phoneNumber: '211-345-6790');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('phoneNumber', $e->getErrors());
            self::assertStringContainsString('digits only', $e->getErrors()['phoneNumber']);
        }
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new Address(emailAddress: 'not-an-email');
    }

    public function testOverlongLine1IsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new Address(line1: str_repeat('a', 31));
    }

    /**
     * PCRE's `$` also matches immediately before a trailing newline, so patterns
     * anchored with it accept "Paris\n" — the shape a value takes when it comes
     * from a pasted form field, a spreadsheet cell or a config file. Every
     * pattern here anchors with \z instead.
     *
     * @dataProvider fieldsWithCharacterConstraints
     */
    public function testTrailingNewlineIsRejected(string $field, string $valid): void
    {
        try {
            new Address(...[$field => $valid . "\n"]);
            self::fail("Expected ValidationException for {$field} with a trailing newline");
        } catch (ValidationException $e) {
            self::assertArrayHasKey($field, $e->getErrors());
        }
    }

    /**
     * The same values without the newline must still be accepted, so the fix is
     * an anchoring change and not an accidental tightening.
     *
     * @dataProvider fieldsWithCharacterConstraints
     */
    public function testCleanValueIsStillAccepted(string $field, string $valid): void
    {
        $address = new Address(...[$field => $valid]);

        self::assertSame($valid, $address->{$field});
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fieldsWithCharacterConstraints(): array
    {
        return [
            'line1'        => ['line1', 'Paris'],
            'line2'        => ['line2', 'Apt 4'],
            'city'         => ['city', 'Kingston'],
            'state'        => ['state', 'JM-01'],
            'county'       => ['county', 'St. Andrew'],
            'firstName'    => ['firstName', 'Jane'],
            'lastName'     => ['lastName', 'Doe'],
            'postalCode'   => ['postalCode', 'M5V3A8'],
            'phoneNumber'  => ['phoneNumber', '35301176543210'],
            'phoneNumber2' => ['phoneNumber2', '18765550100'],
            'phoneNumber3' => ['phoneNumber3', '18765550101'],
        ];
    }

    /**
     * A newline in the middle was already rejected; guard against a fix that
     * only handles the trailing case.
     */
    public function testEmbeddedNewlineIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new Address(line1: "Paris\nLondon");
    }
}
