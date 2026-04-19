<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway;

use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayConfig;
use Lysak\PhpTestTaskPaymentGateway\Exception\RequestFailedException;
use Lysak\PhpTestTaskPaymentGateway\Exception\TransportException;
use Lysak\PhpTestTaskPaymentGateway\Http\SignedRequestBuilder;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

class PaymentGatewayClient
{
    private readonly SignedRequestBuilder $requestBuilder;

    public function __construct(
        private readonly PaymentGatewayConfig $config,
        private readonly ClientInterface $httpClient,
        RequestFactoryInterface&StreamFactoryInterface $httpFactory,
    ) {
        $this->requestBuilder = new SignedRequestBuilder($httpFactory);
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function send(string $httpMethod, string $endpointUrl, array $payload): ResponseInterface
    {
        $request = $this->requestBuilder->build(
            $httpMethod,
            $endpointUrl,
            $payload,
            $this->config->clientId(),
            $this->config->hmacSecret(),
        );

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            if ($exception instanceof TransportException) {
                throw $exception;
            }

            throw new TransportException($exception);
        }

        if (
            // 2xx range
            $response->getStatusCode() < Response::HTTP_OK
            || $response->getStatusCode() >= Response::HTTP_MULTIPLE_CHOICES
        ) {
            throw new RequestFailedException($response);
        }

        return $response;
    }
}
