<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

/**
 * Non-financial 3DS pre-authentication and fraud check request.
 *
 * Use this to perform a 3DS check and risk assessment without moving any money.
 * Typically used before initiating a payment to assess fraud risk.
 *
 * Corresponds to POST /spi/riskmgmt.
 */
final class RiskManagementRequest extends SpiRequest
{
}
