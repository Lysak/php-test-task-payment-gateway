<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class TransportException extends RuntimeException implements ClientExceptionInterface, PaymentGatewayExceptionInterface
{
    public function __construct(ClientExceptionInterface $exception)
    {
        parent::__construct('Payment gateway transport failed.', previous: $exception);
    }
}
