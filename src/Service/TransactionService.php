<?php

declare(strict_types=1);

namespace PowerTranz\Service;

use PowerTranz\Model\Request\CaptureRequest;
use PowerTranz\Model\Request\RefundRequest;
use PowerTranz\Model\Request\VoidRequest;
use PowerTranz\Model\Response\CaptureResponse;
use PowerTranz\Model\Response\RefundResponse;
use PowerTranz\Model\Response\VoidResponse;

/**
 * Service for post-authorisation transaction operations.
 */
final class TransactionService extends AbstractService
{
    /**
     * Capture previously authorised funds.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function capture(CaptureRequest $request): CaptureResponse
    {
        $data = $this->post('capture', $request);

        return CaptureResponse::fromArray($data);
    }

    /**
     * Refund a previously captured transaction, in full or partially.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function refund(RefundRequest $request): RefundResponse
    {
        $data = $this->post('refund', $request);

        return RefundResponse::fromArray($data);
    }

    /**
     * Void (cancel) an authorised or captured transaction before settlement.
     *
     * @throws \PowerTranz\Exception\AuthenticationException
     * @throws \PowerTranz\Exception\ApiException
     * @throws \PowerTranz\Exception\NetworkException
     */
    public function void(VoidRequest $request): VoidResponse
    {
        $data = $this->post('void', $request);

        return VoidResponse::fromArray($data);
    }
}
