<?php

declare(strict_types=1);

namespace PowerTranz\Model\Request;

/**
 * Authorise and capture funds in a single step.
 *
 * Use {@see AuthRequest} when you need to authorise first and capture later.
 *
 * Corresponds to POST /spi/sale.
 */
final class SaleRequest extends SpiRequest
{
}
