<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Http;

use InvalidArgumentException;
use Lysak\PhpTestTaskPaymentGateway\Security\AmexMacHeaderBuilder;
use Lysak\PhpTestTaskPaymentGateway\Security\NonceGenerator;
use Lysak\PhpTestTaskPaymentGateway\Support\CanonicalPayloadQueryBuilder;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class SignedRequestBuilder
{
    private const string DEFAULT_SCHEME = 'https';
    private const string DEFAULT_PATH = '/';
    private const int PORT_HTTPS = 443;
    private const int PORT_HTTP = 80;

    /** HTTP methods where payload is sent as a query string */
    private const array QUERY_METHODS = [Request::METHOD_GET, Request::METHOD_DELETE];

    /** HTTP methods where payload is sent as JSON body */
    private const array BODY_METHODS = [Request::METHOD_POST, Request::METHOD_PUT];

    /**
     * @param  RequestFactoryInterface&StreamFactoryInterface&UriFactoryInterface  $httpFactory
     * @param  AmexMacHeaderBuilder  $macHeaderBuilder
     * @param  CanonicalPayloadQueryBuilder  $queryBuilder
     * @param  NonceGenerator  $nonceGenerator
     */
    public function __construct(
        private readonly RequestFactoryInterface&StreamFactoryInterface&UriFactoryInterface $httpFactory,
        private readonly AmexMacHeaderBuilder $macHeaderBuilder = new AmexMacHeaderBuilder(),
        private readonly CanonicalPayloadQueryBuilder $queryBuilder = new CanonicalPayloadQueryBuilder(),
        private readonly NonceGenerator $nonceGenerator = new NonceGenerator(),
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidArgumentException when $endpointUrl already contains a query string.
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function build(
        string $httpMethod,
        string $endpointUrl,
        array $payload,
        string $clientId,
        string $secret,
    ): RequestInterface {
        $this->guardAgainstExistingQuery($endpointUrl);

        $httpMethod = strtoupper($httpMethod);

        return match (true) {
            $httpMethod === Request::METHOD_HEAD => $this->buildHeadRequest($endpointUrl, $clientId, $secret),
            $this->isQueryMethod($httpMethod) => $this->buildQueryRequest($httpMethod, $endpointUrl, $payload, $clientId, $secret),
            $this->isBodyMethod($httpMethod) => $this->buildBodyRequest($httpMethod, $endpointUrl, $payload, $clientId, $secret),
            default => throw new InvalidArgumentException(
                \sprintf('Unsupported HTTP method: %s. Allowed: HEAD, GET, DELETE, POST, PUT.', $httpMethod),
            ),
        };
    }

    /**
     * HEAD — 4-component MAC header (no bodyhash).
     */
    private function buildHeadRequest(
        string $endpointUrl,
        string $clientId,
        string $secret,
    ): RequestInterface {
        $urlParts = $this->parseUrl($endpointUrl);

        $authHeader = $this->macHeaderBuilder->buildWithoutBodyhash(
            $clientId,
            $secret,
            Request::METHOD_HEAD,
            $urlParts['host'],
            $urlParts['port'],
            $urlParts['resourcePath'],
            $this->generateTimestamp(),
            $this->nonceGenerator->generate(),
        );

        return $this->httpFactory
            ->createRequest('HEAD', $endpointUrl)
            ->withHeader('Authorization', $authHeader);
    }

    /**
     * GET, DELETE — payload in query string, bodyhash over canonical query string.
     *
     * @param array<array-key, mixed> $payload
     */
    private function buildQueryRequest(
        string $httpMethod,
        string $endpointUrl,
        array $payload,
        string $clientId,
        string $secret,
    ): RequestInterface {
        $queryString = $this->queryBuilder->build($payload);
        $requestUrl = $queryString === '' ? $endpointUrl : $endpointUrl . '?' . $queryString;

        $urlParts = $this->parseUrl($requestUrl);

        $authHeader = $this->macHeaderBuilder->buildWithBodyhash(
            $clientId,
            $secret,
            $httpMethod,
            $urlParts['host'],
            $urlParts['port'],
            $urlParts['resourcePath'],
            $queryString,
            $this->generateTimestamp(),
            $this->nonceGenerator->generate(),
        );

        return $this->httpFactory
            ->createRequest($httpMethod, $requestUrl)
            ->withHeader('Authorization', $authHeader);
    }

    /**
     * POST, PUT — payload in JSON body, bodyhash over raw JSON.
     *
     * @param array<array-key, mixed> $payload
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function buildBodyRequest(
        string $httpMethod,
        string $endpointUrl,
        array $payload,
        string $clientId,
        string $secret,
    ): RequestInterface {
        $jsonBody = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $urlParts = $this->parseUrl($endpointUrl);

        $authHeader = $this->macHeaderBuilder->buildWithBodyhash(
            $clientId,
            $secret,
            $httpMethod,
            $urlParts['host'],
            $urlParts['port'],
            $urlParts['resourcePath'],
            $jsonBody,
            $this->generateTimestamp(),
            $this->nonceGenerator->generate(),
        );

        return $this->httpFactory
            ->createRequest($httpMethod, $endpointUrl)
            ->withHeader('Authorization', $authHeader)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->httpFactory->createStream($jsonBody));
    }

    /**
     * Parse a URL into host, port, and resourcePath components.
     *
     * @return array{host: string, port: int, resourcePath: string}
     */
    private function parseUrl(string $url): array
    {
        $uri = $this->httpFactory->createUri($url);

        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : self::DEFAULT_SCHEME;
        $host = $uri->getHost();
        $port = $uri->getPort() ?? ($scheme === self::DEFAULT_SCHEME ? self::PORT_HTTPS : self::PORT_HTTP);
        $path = $uri->getPath() !== '' ? $uri->getPath() : self::DEFAULT_PATH;
        $query = $uri->getQuery() !== '' ? '?' . $uri->getQuery() : '';

        return [
            'host' => $host,
            'port' => $port,
            'resourcePath' => $path . $query,
        ];
    }

    /**
     * Unix epoch timestamp in milliseconds (Amex spec).
     */
    private function generateTimestamp(): string
    {
        return (string) \intval(microtime(true) * 1000);
    }

    private function isQueryMethod(string $httpMethod): bool
    {
        return \in_array($httpMethod, self::QUERY_METHODS, true);
    }

    private function isBodyMethod(string $httpMethod): bool
    {
        return \in_array($httpMethod, self::BODY_METHODS, true);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardAgainstExistingQuery(string $endpointUrl): void
    {
        if ($this->httpFactory->createUri($endpointUrl)->getQuery() !== '') {
            throw new InvalidArgumentException(
                'Endpoint URL must not contain a query string; all parameters must be passed via $payload so the HMAC signature covers them.',
            );
        }
    }
}
