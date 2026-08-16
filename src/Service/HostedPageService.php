<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use Brick\Money\Money;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\Parts\Address;
use PowerTranz\Model\Request\Parts\ExtendedData;
use PowerTranz\Model\Request\Parts\HostedPage;
use PowerTranz\Model\Request\Parts\ThreeDSecure;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\AuthResponse;
use PowerTranz\Model\Response\SaleResponse;
use PowerTranz\Model\Response\ThreeDSecureChallenge;

/**
 * Hosted Payment Page (HPP) transactions.
 *
 * HPP keeps card entry off your servers: the cardholder types their card into a
 * PowerTranz-hosted form, which reduces your PCI DSS scope for card data.
 *
 * ## There is no separate HPP endpoint
 *
 * An HPP transaction is an ordinary /spi/sale or /spi/auth carrying
 * {@code ExtendedData.HostedPage} and **no Source**. The gateway responds with
 * IsoResponseCode SP4 and RedirectData containing the payment form, which you
 * render in an iframe exactly as with a 3DS challenge.
 *
 * This service exists to make that shape correct by construction — it builds the
 * request so a Source can never be sent alongside hosted-page parameters, and so
 * the required MerchantResponseUrl is never forgotten.
 *
 * ## Flow
 *
 *   1. Call {@see sale()} or {@see authorize()}.
 *   2. Render the returned {@see ThreeDSecureChallenge::iframe()} in your page.
 *   3. The cardholder enters their card and completes any 3DS challenge.
 *   4. PowerTranz POSTs the result to your MerchantResponseUrl.
 *   5. Complete the payment with {@see SpiService::payment()} and the SpiToken.
 *
 * @see https://developer.powertranz.com/docs/spi-3ds-hpp-1
 */
final class HostedPageService
{
    public function __construct(private readonly SpiService $spi)
    {
    }

    /**
     * Start a hosted-page sale (authorise and capture in one step).
     *
     * Returns a {@see ThreeDSecureChallenge} in the normal case — the hosted page
     * itself arrives as RedirectData, so a redirect is always expected. A direct
     * {@see SaleResponse} means the gateway rejected the request before
     * preprocessing; check {@see SaleResponse::$responseMessage}.
     *
     * @param  string $merchantResponseUrl Where PowerTranz posts the result and
     *                                     returns the cardholder. Must be
     *                                     reachable by PowerTranz.
     * @throws \PowerTranz\Exception\ValidationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function sale(
        Money $totalAmount,
        string $orderIdentifier,
        HostedPage $page,
        string $merchantResponseUrl,
        bool $threeDSecure = true,
        ?ThreeDSecure $threeDSecureParameters = null,
        ?Address $billingAddress = null,
        ?string $transactionIdentifier = null,
    ): SaleResponse|ThreeDSecureChallenge {
        return $this->spi->sale(new SaleRequest(
            totalAmount:           $totalAmount,
            orderIdentifier:       $orderIdentifier,
            transactionIdentifier: $transactionIdentifier,
            threeDSecure:          $threeDSecure,
            extendedData:          $this->buildExtendedData(
                $merchantResponseUrl,
                $page,
                $threeDSecure,
                $threeDSecureParameters,
            ),
            billingAddress:        $billingAddress,
        ));
    }

    /**
     * Start a hosted-page authorisation, to be captured later with
     * {@see TransactionService::capture()}.
     *
     * @throws \PowerTranz\Exception\ValidationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function authorize(
        Money $totalAmount,
        string $orderIdentifier,
        HostedPage $page,
        string $merchantResponseUrl,
        bool $threeDSecure = true,
        ?ThreeDSecure $threeDSecureParameters = null,
        ?Address $billingAddress = null,
        ?string $transactionIdentifier = null,
    ): AuthResponse|ThreeDSecureChallenge {
        return $this->spi->authorize(new AuthRequest(
            totalAmount:           $totalAmount,
            orderIdentifier:       $orderIdentifier,
            transactionIdentifier: $transactionIdentifier,
            threeDSecure:          $threeDSecure,
            extendedData:          $this->buildExtendedData(
                $merchantResponseUrl,
                $page,
                $threeDSecure,
                $threeDSecureParameters,
            ),
            billingAddress:        $billingAddress,
        ));
    }

    /**
     * Build the ExtendedData for a hosted page, keeping it consistent with the
     * top-level 3DS flag.
     *
     * A hosted page always needs the MerchantResponseUrl, with or without 3DS.
     * What must not happen is 3DS parameters travelling alongside a false flag:
     * the gateway accepts that combination and quietly skips authentication.
     * With 3DS off the parameters are therefore omitted — except when the caller
     * passed some explicitly, which is a contradiction worth surfacing rather
     * than silently resolving, so they are passed through for
     * {@see \PowerTranz\Model\Request\SpiRequest} to reject.
     */
    private function buildExtendedData(
        string $merchantResponseUrl,
        HostedPage $page,
        bool $threeDSecure,
        ?ThreeDSecure $threeDSecureParameters,
    ): ExtendedData {
        if ($threeDSecure) {
            return ExtendedData::forHostedPage(
                merchantResponseUrl: $merchantResponseUrl,
                hostedPage:          $page,
                threeDSecure:        $threeDSecureParameters,
            );
        }

        return new ExtendedData(
            merchantResponseUrl: $merchantResponseUrl,
            threeDSecure:        $threeDSecureParameters,
            hostedPage:          $page,
        );
    }
}
