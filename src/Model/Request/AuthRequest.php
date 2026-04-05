<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

/**
 * Authorise a transaction without capturing funds.
 *
 * Use {@see SaleRequest} when you want to authorise and capture in a single step.
 * Follow up with a {@see CaptureRequest} to capture the reserved funds.
 *
 * Corresponds to POST /spi/auth.
 */
final class AuthRequest extends SpiRequest
{
}
