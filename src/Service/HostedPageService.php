<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use PowerTranz\Config\Configuration;
use PowerTranz\Exception\ValidationException;

/**
 * Service for PowerTranz Hosted Payment Page (HPP) integration.
 *
 * HPP offloads card data entry to a PowerTranz-hosted page, removing
 * your checkout from PCI DSS scope for card data handling.
 *
 * Basic flow:
 *   1. Call {@see buildRedirectUrl()} to get the HPP URL.
 *   2. Redirect the customer to that URL.
 *   3. PowerTranz processes the payment and redirects the customer
 *      back to your $returnUrl with the transaction result.
 */
final class HostedPageService
{
    public function __construct(private readonly Configuration $config)
    {
    }

    /**
     * Build the redirect URL for a Hosted Payment Page session.
     *
     * @param string      $orderIdentifier  Your unique order reference.
     * @param float       $totalAmount      Amount to charge.
     * @param string      $currencyCode     ISO 4217 numeric currency code (e.g. '840' for USD).
     * @param string      $returnUrl        URL to redirect the customer to after payment.
     * @param string|null $pageSetName      HPP page set name configured in the merchant portal.
     * @param string|null $pageName         HPP page name within the page set.
     * @param array<string, string> $extra  Additional query parameters.
     */
    public function buildRedirectUrl(
        string $orderIdentifier,
        float $totalAmount,
        string $currencyCode,
        string $returnUrl,
        ?string $pageSetName = null,
        ?string $pageName = null,
        array $extra = [],
    ): string {
        if (trim($orderIdentifier) === '') {
            throw new ValidationException('OrderIdentifier must not be empty.');
        }

        if ($totalAmount <= 0) {
            throw new ValidationException('TotalAmount must be greater than zero.');
        }

        if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            throw new ValidationException('ReturnUrl must be a valid URL.');
        }

        $baseUrl = $this->config->environment->isSandbox()
            ? 'https://staging.ptranz.com/hpp/'
            : 'https://hpp.ptranz.com/hpp/';

        $params = array_merge([
            'PowerTranzId'      => $this->config->powerTranzId,
            'OrderIdentifier'   => $orderIdentifier,
            'TotalAmount'       => number_format($totalAmount, 2, '.', ''),
            'CurrencyCode'      => $currencyCode,
            'ReturnUrl'         => $returnUrl,
        ], $extra);

        if ($pageSetName !== null) {
            $params['PageSetName'] = $pageSetName;
        }

        if ($pageName !== null) {
            $params['PageName'] = $pageName;
        }

        return $baseUrl . '?' . http_build_query($params);
    }
}
