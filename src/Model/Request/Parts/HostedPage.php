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

    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim', message: 'PageSet must not be empty.')]
        public readonly string $pageSet,

        #[Assert\NotBlank(normalizer: 'trim', message: 'PageName must not be empty.')]
        public readonly string $pageName,
    ) {
        RequestValidator::validate($this, 'Hosted page validation failed.');
    }

    /**
     * Build page parameters for a page set created in the Merchant Portal,
     * applying the mandatory {@code PTZ/} prefix.
     *
     * Portal-created pages fail to load if the prefix is missing, and the error
     * surfaces as a failed transaction rather than a clear message — so prefer
     * this factory over passing the prefix by hand.
     */
    public static function fromPortal(string $pageSet, string $pageName): self
    {
        return new self(
            pageSet:  str_starts_with($pageSet, self::PORTAL_PREFIX) ? $pageSet : self::PORTAL_PREFIX . $pageSet,
            pageName: $pageName,
        );
    }

    public function jsonSerialize(): mixed
    {
        return [
            'PageSet'  => $this->pageSet,
            'PageName' => $this->pageName,
        ];
    }
}
