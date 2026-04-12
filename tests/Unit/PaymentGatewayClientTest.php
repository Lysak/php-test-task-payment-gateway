<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayConfig;
use Lysak\PhpTestTaskPaymentGateway\Exception\RequestFailedException;
use Lysak\PhpTestTaskPaymentGateway\Exception\TransportException;
use Lysak\PhpTestTaskPaymentGateway\PaymentGatewayClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the error-path contract of PaymentGatewayClient::send() without
 * hitting the network. The happy path against a real mTLS endpoint is
 * covered by BadSslMtlsIntegrationTest.
 */
#[CoversClass(PaymentGatewayClient::class)]
class PaymentGatewayClientTest extends TestCase
{
    private const string ENDPOINT = 'https://api.example.test/payments';

    /** @var array<string, string> */
    private const array PAYLOAD = [
        'transaction_id' => '12345',
        'amount' => '99.99',
        'currency' => 'USD',
    ];

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function successfulStatusCodeProvider(): iterable
    {
        yield '200 OK' => [200];
        yield '201 Created' => [201];
        yield '204 No Content' => [204];
        yield '299 edge of 2xx' => [299];
    }

    #[DataProvider('successfulStatusCodeProvider')]
    public function testItReturnsResponseForAnyTwoXxStatus(int $statusCode): void
    {
        $expectedResponse = new Response($statusCode);
        $client = $this->buildClient(new FakeReturningHttpClient($expectedResponse));

        $actual = $client->send(Request::METHOD_GET, self::ENDPOINT, self::PAYLOAD);

        self::assertSame($expectedResponse, $actual);
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function nonSuccessStatusCodeProvider(): iterable
    {
        yield '199 below 2xx' => [199];
        yield '301 redirect' => [301];
        yield '400 bad request' => [400];
        yield '401 unauthorized' => [401];
        yield '404 not found' => [404];
        yield '500 internal' => [500];
        yield '503 unavailable' => [503];
    }

    #[DataProvider('nonSuccessStatusCodeProvider')]
    public function testItThrowsRequestFailedExceptionForAnyNonTwoXxStatus(int $statusCode): void
    {
        $response = new Response($statusCode);
        $client = $this->buildClient(new FakeReturningHttpClient($response));

        try {
            $client->send(Request::METHOD_GET, self::ENDPOINT, self::PAYLOAD);
            self::fail('Expected RequestFailedException for status ' . $statusCode);
        } catch (RequestFailedException $exception) {
            self::assertSame($statusCode, $exception->statusCode());
            self::assertSame($response, $exception->response());
        }
    }

    public function testItWrapsPsr18ClientExceptionInTransportException(): void
    {
        $originalException = new class ('connection reset') extends RuntimeException implements ClientExceptionInterface {
        };

        $client = $this->buildClient(new FakeThrowingHttpClient($originalException));

        try {
            $client->send(Request::METHOD_GET, self::ENDPOINT, self::PAYLOAD);
            self::fail('Expected TransportException to be thrown.');
        } catch (TransportException $exception) {
            self::assertSame(
                $originalException,
                $exception->getPrevious(),
                'Original PSR-18 exception must be preserved as previous.',
            );
        }
    }

    public function testItRethrowsTransportExceptionWithoutDoubleWrapping(): void
    {
        // If the injected PSR-18 client (or a decorator on top of it) itself
        // emits our TransportException, we must not wrap it again — otherwise
        // $e->getPrevious() stops being the real cause.
        $innerCause = new class ('io') extends RuntimeException implements ClientExceptionInterface {
        };
        $transportException = new TransportException($innerCause);

        $client = $this->buildClient(new FakeThrowingHttpClient($transportException));

        try {
            $client->send(Request::METHOD_GET, self::ENDPOINT, self::PAYLOAD);
            self::fail('Expected TransportException to propagate.');
        } catch (TransportException $caught) {
            self::assertSame($transportException, $caught);
            self::assertSame($innerCause, $caught->getPrevious());
        }
    }

    private function buildClient(ClientInterface $httpClient): PaymentGatewayClient
    {
        return new PaymentGatewayClient(
            new PaymentGatewayConfig('test-client-id', 'test-secret'),
            $httpClient,
            new HttpFactory(),
        );
    }
}

final class FakeReturningHttpClient implements ClientInterface
{
    public function __construct(private readonly ResponseInterface $response)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

final class FakeThrowingHttpClient implements ClientInterface
{
    public function __construct(private readonly ClientExceptionInterface $exception)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw $this->exception;
    }
}
