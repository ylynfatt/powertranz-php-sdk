<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

use Brick\Money\Money;
use PowerTranz\Model\Request\Parts\Address;
use PowerTranz\Model\Request\Parts\CardSource;
use PowerTranz\Model\Request\Parts\ExtendedData;
use PowerTranz\Model\Request\Parts\TokenSource;

/**
 * Non-financial request: 3DS authentication and/or tokenisation.
 *
 * No funds move. Use it to authenticate the cardholder without charging them, or
 * to tokenise a card for later use — the authentication values returned can then
 * be sent with the financial endpoints.
 *
 * ## Tokenize lives here, and only here
 *
 * {@see $tokenize} is documented on /spi/RiskMgmt but not on /spi/Sale or
 * /spi/Auth, so it is declared on this subclass rather than on
 * {@see SpiRequest}. To tokenise a card, send a RiskMgmt request with
 * {@code tokenize: true} and read {@code PanToken} from the response.
 *
 * Corresponds to POST /spi/riskmgmt.
 *
 * @see https://developer.powertranz.com/reference/post_spi-riskmgmt
 */
final class RiskManagementRequest extends SpiRequest
{
    public function __construct(
        Money $totalAmount,
        string $orderIdentifier,
        CardSource|TokenSource|null $source = null,
        ?string $transactionIdentifier = null,
        bool $threeDSecure = false,
        ?ExtendedData $extendedData = null,
        ?Address $billingAddress = null,
        ?Address $shippingAddress = null,
        ?bool $addressMatch = null,

        /**
         * If true, the gateway tokenises the card and returns the token as
         * PanToken on the response.
         */
        public readonly bool $tokenize = false,
    ) {
        parent::__construct(
            totalAmount:           $totalAmount,
            orderIdentifier:       $orderIdentifier,
            source:                $source,
            transactionIdentifier: $transactionIdentifier,
            threeDSecure:          $threeDSecure,
            extendedData:          $extendedData,
            billingAddress:        $billingAddress,
            shippingAddress:       $shippingAddress,
            addressMatch:          $addressMatch,
        );
    }

    public function jsonSerialize(): mixed
    {
        $data = parent::jsonSerialize();

        if ($this->tokenize) {
            $data['Tokenize'] = true;
        }

        return $data;
    }
}
