<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use Brick\Money\Money;
use PowerTranz\Config\Configuration;
use PowerTranz\Enum\CurrencyCode;
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
     * The {@see $totalAmount} is a {@see Money} object — the currency code
     * is derived from it automatically, so no separate currency parameter
     * is needed.
     *
     * @param  string               $orderIdentifier  Your unique order reference.
     * @param  Money                $totalAmount       Amount and currency to charge.
     * @param  string               $returnUrl         URL to redirect the customer to after payment.
     * @param  string|null          $pageSetName       HPP page set name configured in the merchant portal.
     * @param  string|null          $pageName          HPP page name within the page set.
     * @param  array<string, string> $extra            Additional query parameters.
     */
    public function buildRedirectUrl(
        string $orderIdentifier,
        Money $totalAmount,
        string $returnUrl,
        ?string $pageSetName = null,
        ?string $pageName = null,
        array $extra = [],
    ): string {
        if (trim($orderIdentifier) === '') {
            throw new ValidationException('OrderIdentifier must not be empty.');
        }

        if (!$totalAmount->isPositive()) {
            throw new ValidationException('TotalAmount must be greater than zero.');
        }

        if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            throw new ValidationException('ReturnUrl must be a valid URL.');
        }

        $alpha       = $totalAmount->getCurrency()->getCurrencyCode();
        $currencyNum = $this->resolveCurrencyNumeric($alpha);
        $amountStr   = (string) $totalAmount->getAmount();

        $baseUrl = $this->config->environment->isSandbox()
            ? 'https://staging.ptranz.com/hpp/'
            : 'https://hpp.ptranz.com/hpp/';

        $params = array_merge([
            'PowerTranzId'    => $this->config->powerTranzId,
            'OrderIdentifier' => $orderIdentifier,
            'TotalAmount'     => $amountStr,
            'CurrencyCode'    => $currencyNum,
            'ReturnUrl'       => $returnUrl,
        ], $extra);

        if ($pageSetName !== null) {
            $params['PageSetName'] = $pageSetName;
        }

        if ($pageName !== null) {
            $params['PageName'] = $pageName;
        }

        return $baseUrl . '?' . http_build_query($params);
    }

    private function resolveCurrencyNumeric(string $alpha): string
    {
        try {
            return CurrencyCode::fromAlphaCode($alpha)->numericString();
        } catch (\ValueError) {
            return $alpha;
        }
    }
}
