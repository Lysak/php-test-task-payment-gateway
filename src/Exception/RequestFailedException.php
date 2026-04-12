<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class RequestFailedException extends RuntimeException implements PaymentGatewayExceptionInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
        parent::__construct(
            \sprintf(
                'Payment gateway request failed with HTTP status %d.',
                $response->getStatusCode(),
            ),
            $response->getStatusCode(),
        );
    }

    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function response(): ResponseInterface
    {
        return $this->response;
    }
}
