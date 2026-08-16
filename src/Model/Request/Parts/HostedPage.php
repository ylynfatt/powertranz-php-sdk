<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request\Parts;

use JsonSerializable;
use PowerTranz\Validator\RequestValidator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Hosted Payment Page parameters, sent as {@code ExtendedData.HostedPage}.
 *
 * When present, the gateway returns RedirectData containing the hosted payment
 * form rather than expecting card details in the request — so the {@code Source}
 * object is omitted entirely and the cardholder types their card into the iframe.
 *
 * @see https://developer.powertranz.com/docs/spi-3ds-hpp-1
 */
final class HostedPage implements JsonSerializable
{
    /** Required prefix for page sets created in the Powertranz Merchant Portal. */
    public const PORTAL_PREFIX = 'PTZ/';

    #[Assert\NotBlank(normalizer: 'trim', message: 'PageSet must not be empty.')]
    public readonly string $pageSet;

    #[Assert\NotBlank(normalizer: 'trim', message: 'PageName must not be empty.')]
    public readonly string $pageName;

    /**
     * Both values are trimmed before use. Surrounding whitespace — from a config
     * file, an environment variable, a copy-paste out of the Merchant Portal —
     * is carried into the request verbatim otherwise, and the page then fails to
     * load with nothing to indicate why.
     */
    public function __construct(string $pageSet, string $pageName)
    {
        $this->pageSet  = trim($pageSet);
        $this->pageName = trim($pageName);

        RequestValidator::validate($this, 'Hosted page validation failed.');
    }

    /**
     * Build page parameters for a page set created in the Merchant Portal,
     * applying the mandatory {@code PTZ/} prefix.
     *
     * Portal-created pages fail to load if the prefix is missing, and the error
     * surfaces as a failed transaction rather than a clear message — so prefer
     * this factory over passing the prefix by hand.
     *
     * The page set may be given with or without the prefix, in any case: an
     * existing one is normalised to {@see PORTAL_PREFIX} rather than stacked, so
     * {@code 'MySet'}, {@code 'PTZ/MySet'} and {@code 'ptz/MySet'} all yield
     * {@code 'PTZ/MySet'}.
     */
    public static function fromPortal(string $pageSet, string $pageName): self
    {
        $bare = self::stripPortalPrefix(trim($pageSet));

        // Check what is left after the prefix is removed. Checking the prefixed
        // value instead would see 'PTZ/' at worst — not blank, so an empty page
        // set read from missing configuration would sail through the constructor
        // and be reported only as a failed transaction.
        RequestValidator::validateValue(
            $bare,
            new Assert\NotBlank(normalizer: 'trim', message: 'PageSet must not be empty.'),
            'pageSet',
            'Hosted page validation failed.',
        );

        return new self(
            pageSet:  self::PORTAL_PREFIX . $bare,
            pageName: $pageName,
        );
    }

    /**
     * Remove a leading portal prefix, matching case-insensitively so that
     * {@code 'ptz/MySet'} is recognised as already prefixed. A case-sensitive
     * check would treat it as bare and produce {@code 'PTZ/ptz/MySet'}.
     */
    private static function stripPortalPrefix(string $pageSet): string
    {
        $length = strlen(self::PORTAL_PREFIX);

        if (strncasecmp($pageSet, self::PORTAL_PREFIX, $length) !== 0) {
            return $pageSet;
        }

        return trim(substr($pageSet, $length));
    }

    public function jsonSerialize(): mixed
    {
        return [
            'PageSet'  => $this->pageSet,
            'PageName' => $this->pageName,
        ];
    }
}
