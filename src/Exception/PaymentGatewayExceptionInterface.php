<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Exception;

use Throwable;

/**
 * Marker interface shared by every exception this package throws.
 *
 * Integrators can catch `PaymentGatewayExceptionInterface` to handle all
 * package failures uniformly without enumerating concrete exception types.
 */
interface PaymentGatewayExceptionInterface extends Throwable
{
}
