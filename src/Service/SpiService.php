<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use PowerTranz\Enum\IsoResponseCode;
use PowerTranz\Model\Request\AuthRequest;
use PowerTranz\Model\Request\PaymentRequest;
use PowerTranz\Model\Request\RiskManagementRequest;
use PowerTranz\Model\Request\SaleRequest;
use PowerTranz\Model\Response\AuthResponse;
use PowerTranz\Model\Response\PaymentResponse;
use PowerTranz\Model\Response\RiskManagementResponse;
use PowerTranz\Model\Response\SaleResponse;
use PowerTranz\Model\Response\ThreeDSecureChallenge;

/**
 * Service for SPI (Secure Payment Interface) operations.
 *
 * Methods that initiate a charge return a union type:
 *   - The concrete response (e.g. SaleResponse) on approval/decline
 *   - A {@see ThreeDSecureChallenge} when 3DS authentication is required (IsoResponseCode 3D0)
 *
 * The union return type forces calling code to handle both branches at compile time
 * (with PHPStan/Psalm), eliminating silent 3DS bypass bugs.
 */
final class SpiService extends AbstractService
{
    /**
     * Authorise a transaction without capturing funds.
     *
     * Follow up with {@see \PowerTranz\Service\TransactionService::capture()} to
     * capture the reserved funds.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function authorize(AuthRequest $request): AuthResponse|ThreeDSecureChallenge
    {
        $data    = $this->post('spi/auth', $request);
        $isoCode = IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''));

        if ($isoCode === IsoResponseCode::THREE_DS_REDIRECT) {
            return ThreeDSecureChallenge::fromArray($data);
        }

        return AuthResponse::fromArray($data);
    }

    /**
     * Authorise and capture funds in a single step.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function sale(SaleRequest $request): SaleResponse|ThreeDSecureChallenge
    {
        $data    = $this->post('spi/sale', $request);
        $isoCode = IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''));

        if ($isoCode === IsoResponseCode::THREE_DS_REDIRECT) {
            return ThreeDSecureChallenge::fromArray($data);
        }

        return SaleResponse::fromArray($data);
    }

    /**
     * Non-financial 3DS pre-authentication and fraud risk assessment.
     *
     * Does not move any funds. Use to assess fraud risk before committing to a charge.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function riskManagement(RiskManagementRequest $request): RiskManagementResponse|ThreeDSecureChallenge
    {
        $data    = $this->post('spi/riskmgmt', $request);
        $isoCode = IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''));

        if ($isoCode === IsoResponseCode::THREE_DS_REDIRECT) {
            return ThreeDSecureChallenge::fromArray($data);
        }

        return RiskManagementResponse::fromArray($data);
    }

    /**
     * Complete a payment after a successful 3DS challenge.
     *
     * Call this with the SpiToken from a {@see ThreeDSecureChallenge} response
     * after the cardholder has completed the 3DS challenge. SpiTokens expire
     * after 5 minutes.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\TokenExpiredException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function payment(PaymentRequest $request): PaymentResponse
    {
        $data = $this->post('spi/payment', $request);

        return PaymentResponse::fromArray($data);
    }
}
