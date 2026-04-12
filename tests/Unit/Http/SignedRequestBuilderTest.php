<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Http;

use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use Lysak\PhpTestTaskPaymentGateway\Http\SignedRequestBuilder;
use Lysak\PhpTestTaskPaymentGateway\Security\AmexMacHeaderBuilder;
use Lysak\PhpTestTaskPaymentGateway\Security\HmacSignatureGenerator;
use Lysak\PhpTestTaskPaymentGateway\Security\NonceGenerator;
use Lysak\PhpTestTaskPaymentGateway\Support\CanonicalPayloadQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SignedRequestBuilder::class)]
class SignedRequestBuilderTest extends TestCase
{
    private const string CLIENT_ID = 'test-client-id';
    private const string SECRET = 'test-secret';
    public const string NONCE = '00000000-0000-4000-8000-000000000000';
    private const string ENDPOINT = 'https://client.badssl.com/api/payments';

    /** @var array<string, string> */
    private const array PAYLOAD = [
        'transaction_id' => '12345',
        'amount' => '99.99',
        'currency' => 'USD',
    ];

    /** Same PAYLOAD canonicalized: ksort + RFC3986 query string. */
    private const string CANONICAL_QUERY = 'amount=99.99&currency=USD&transaction_id=12345';

    private SignedRequestBuilder $builder;
    private HmacSignatureGenerator $signatureGenerator;

    protected function setUp(): void
    {
        $this->signatureGenerator = new HmacSignatureGenerator();

        $this->builder = new SignedRequestBuilder(
            new HttpFactory(),
            new AmexMacHeaderBuilder($this->signatureGenerator),
            new CanonicalPayloadQueryBuilder(),
            new class () extends NonceGenerator {
                public function generate(): string
                {
                    return SignedRequestBuilderTest::NONCE;
                }
            },
        );
    }

    public function testHeadRequestUsesFourComponentMacHeaderAndCarriesNoPayload(): void
    {
        $request = $this->builder->build(
            Request::METHOD_HEAD,
            self::ENDPOINT,
            [],
            self::CLIENT_ID,
            self::SECRET,
        );

        self::assertSame(Request::METHOD_HEAD, $request->getMethod());
        self::assertSame(self::ENDPOINT, (string) $request->getUri());
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));

        $parts = $this->parseAuthHeaderWithoutBodyhash($request->getHeaderLine('Authorization'));

        self::assertSame(self::CLIENT_ID, $parts['id']);
        self::assertSame(self::NONCE, $parts['nonce']);

        $expectedMacInput = \sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n",
            $parts['ts'],
            self::NONCE,
            Request::METHOD_HEAD,
            '/api/payments',
            'client.badssl.com',
            443,
        );

        self::assertSame(
            $this->signatureGenerator->generate($expectedMacInput, self::SECRET),
            $parts['mac'],
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function queryMethodProvider(): iterable
    {
        yield 'GET' => [Request::METHOD_GET];
        yield 'DELETE' => [Request::METHOD_DELETE];
    }

    #[DataProvider('queryMethodProvider')]
    public function testQueryMethodAppendsCanonicalQueryAndSignsBodyhashOverIt(string $httpMethod): void
    {
        $request = $this->builder->build(
            $httpMethod,
            self::ENDPOINT,
            // Intentionally unsorted to verify canonicalization.
            [
                'currency' => 'USD',
                'transaction_id' => '12345',
                'amount' => '99.99',
            ],
            self::CLIENT_ID,
            self::SECRET,
        );

        self::assertSame($httpMethod, $request->getMethod());
        self::assertSame(
            self::ENDPOINT . '?' . self::CANONICAL_QUERY,
            (string) $request->getUri(),
        );
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Content-Type'));

        $parts = $this->parseAuthHeaderWithBodyhash($request->getHeaderLine('Authorization'));

        $expectedBodyhash = base64_encode(hash('sha256', self::CANONICAL_QUERY, true));
        self::assertSame($expectedBodyhash, $parts['bodyhash']);
        self::assertSame(self::CLIENT_ID, $parts['id']);
        self::assertSame(self::NONCE, $parts['nonce']);

        $expectedMacInput = \sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n%s\n",
            $parts['ts'],
            self::NONCE,
            $httpMethod,
            '/api/payments?' . self::CANONICAL_QUERY,
            'client.badssl.com',
            443,
            $expectedBodyhash,
        );

        self::assertSame(
            $this->signatureGenerator->generate($expectedMacInput, self::SECRET),
            $parts['mac'],
        );
    }

    public function testGetRequestWithEmptyPayloadOmitsQueryStringButStillSigns(): void
    {
        $request = $this->builder->build(
            Request::METHOD_GET,
            self::ENDPOINT,
            [],
            self::CLIENT_ID,
            self::SECRET,
        );

        self::assertSame(self::ENDPOINT, (string) $request->getUri());

        $parts = $this->parseAuthHeaderWithBodyhash($request->getHeaderLine('Authorization'));

        hash('sha256', '', true)
            |> base64_encode(...)
            |> (fn ($x) => self::assertSame($x, $parts['bodyhash'], ));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function bodyMethodProvider(): iterable
    {
        yield 'POST' => [Request::METHOD_POST];
        yield 'PUT' => [Request::METHOD_PUT];
    }

    #[DataProvider('bodyMethodProvider')]
    public function testBodyMethodSendsJsonBodyAndSignsBodyhashOverRawJson(string $httpMethod): void
    {
        $request = $this->builder->build(
            $httpMethod,
            self::ENDPOINT,
            self::PAYLOAD,
            self::CLIENT_ID,
            self::SECRET,
        );

        self::assertSame($httpMethod, $request->getMethod());
        self::assertSame(self::ENDPOINT, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $rawBody = (string) $request->getBody();
        self::assertJson($rawBody);

        $expectedJson = json_encode(self::PAYLOAD, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        self::assertSame($expectedJson, $rawBody, 'POST/PUT must send the raw JSON that was signed.');

        $parts = $this->parseAuthHeaderWithBodyhash($request->getHeaderLine('Authorization'));

        $expectedBodyhash = base64_encode(hash('sha256', $rawBody, true));
        self::assertSame($expectedBodyhash, $parts['bodyhash']);

        $expectedMacInput = \sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n%s\n",
            $parts['ts'],
            self::NONCE,
            $httpMethod,
            '/api/payments',
            'client.badssl.com',
            443,
            $expectedBodyhash,
        );

        self::assertSame(
            $this->signatureGenerator->generate($expectedMacInput, self::SECRET),
            $parts['mac'],
        );
    }

    public function testBuilderAcceptsLowercaseHttpMethod(): void
    {
        $request = $this->builder->build(
            'get',
            self::ENDPOINT,
            self::PAYLOAD,
            self::CLIENT_ID,
            self::SECRET,
        );

        self::assertSame(Request::METHOD_GET, $request->getMethod());
    }

    public function testBuilderRejectsEndpointUrlThatAlreadyContainsQueryString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Endpoint URL must not contain a query string');

        $this->builder->build(
            Request::METHOD_GET,
            self::ENDPOINT . '?prefilled=1',
            self::PAYLOAD,
            self::CLIENT_ID,
            self::SECRET,
        );
    }

    public function testBuilderRejectsUnsupportedHttpMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: PATCH');

        $this->builder->build(
            'PATCH',
            self::ENDPOINT,
            self::PAYLOAD,
            self::CLIENT_ID,
            self::SECRET,
        );
    }

    /**
     * Parse an Amex MAC Authorization header that must contain a bodyhash
     * component (GET, DELETE, POST, PUT).
     *
     * @return array{id: string, ts: string, nonce: string, bodyhash: string, mac: string}
     */
    private function parseAuthHeaderWithBodyhash(string $header): array
    {
        $parts = $this->parseAuthHeaderComponents($header);

        if (!isset($parts['bodyhash'])) {
            self::fail('Expected bodyhash in Authorization header.');
        }

        return [
            'id' => $parts['id'],
            'ts' => $parts['ts'],
            'nonce' => $parts['nonce'],
            'bodyhash' => $parts['bodyhash'],
            'mac' => $parts['mac'],
        ];
    }

    /**
     * Parse an Amex MAC Authorization header that must NOT contain a bodyhash
     * component (HEAD).
     *
     * @return array{id: string, ts: string, nonce: string, mac: string}
     */
    private function parseAuthHeaderWithoutBodyhash(string $header): array
    {
        $parts = $this->parseAuthHeaderComponents($header);

        self::assertArrayNotHasKey('bodyhash', $parts, 'HEAD must not emit bodyhash.');

        return [
            'id' => $parts['id'],
            'ts' => $parts['ts'],
            'nonce' => $parts['nonce'],
            'mac' => $parts['mac'],
        ];
    }

    /**
     * @return array{id: string, ts: string, nonce: string, mac: string, bodyhash?: string}
     */
    private function parseAuthHeaderComponents(string $header): array
    {
        self::assertNotSame('', $header, 'Authorization header must be set.');
        self::assertStringStartsWith('MAC ', $header);

        $matches = [];
        preg_match_all('/(id|ts|nonce|bodyhash|mac)="([^"]*)"/', $header, $matches, \PREG_SET_ORDER);

        $parsed = [];
        foreach ($matches as $match) {
            $parsed[$match[1]] = $match[2];
        }

        self::assertArrayHasKey('id', $parsed);
        self::assertArrayHasKey('ts', $parsed);
        self::assertArrayHasKey('nonce', $parsed);
        self::assertArrayHasKey('mac', $parsed);

        /** @var array{id: string, ts: string, nonce: string, mac: string, bodyhash?: string} $parsed */
        return $parsed;
    }
}
