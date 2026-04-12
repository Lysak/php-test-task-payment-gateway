<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Security;

use Lysak\PhpTestTaskPaymentGateway\Security\AmexMacHeaderBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AmexMacHeaderBuilder::class)]
class AmexMacHeaderBuilderTest extends TestCase
{
    private const string CLIENT_ID = 'test-client-id';
    private const string SECRET = 'test-secret';
    private const string NONCE = '00000000-0000-4000-8000-000000000000';
    private const string TIMESTAMP = '1700000000000';
    private const string HOST = 'client.badssl.com';
    private const int PORT = 443;

    /**
     * Hardcoded expected values computed independently via:
     *
     *   bodyhash = base64(sha256("amount=99.99&currency=USD&transaction_id=12345"))
     *   mac      = base64(hmac_sha256(baseString, "test-secret"))
     *
     * If the algorithm, encoding, or base-string format changes,
     * this test MUST fail — that is its purpose.
     */
    public function testBuildWithBodyhashProducesExpectedHardcodedValues(): void
    {
        $builder = new AmexMacHeaderBuilder();

        $payload = 'amount=99.99&currency=USD&transaction_id=12345';
        $resourcePath = '/api/payments?' . $payload;

        $header = $builder->buildWithBodyhash(
            self::CLIENT_ID,
            self::SECRET,
            'GET',
            self::HOST,
            self::PORT,
            $resourcePath,
            $payload,
            self::TIMESTAMP,
            self::NONCE,
        );

        $expectedBodyhash = 'aZEsUMbikWHVY/3LvSsqEXgGtesJIorpC7vLdzyn2tA=';
        $expectedMac = 'y4AG3FN2LkSUBb/Q6RecV38HXGFecBBmZub85AAcjzY=';

        self::assertSame(
            \sprintf(
                'MAC id="%s",ts="%s",nonce="%s",bodyhash="%s",mac="%s"',
                self::CLIENT_ID,
                self::TIMESTAMP,
                self::NONCE,
                $expectedBodyhash,
                $expectedMac,
            ),
            $header,
        );
    }

    /**
     * bodyhash must be plain SHA-256, NOT HMAC-SHA-256.
     * Changing the secret must NOT change the bodyhash — only the mac.
     */
    public function testBodyhashDoesNotDependOnSecret(): void
    {
        $builder = new AmexMacHeaderBuilder();

        $payload = 'amount=99.99&currency=USD&transaction_id=12345';
        $resourcePath = '/api/payments?' . $payload;

        $headerA = $builder->buildWithBodyhash(
            self::CLIENT_ID,
            'secret-A',
            'GET',
            self::HOST,
            self::PORT,
            $resourcePath,
            $payload,
            self::TIMESTAMP,
            self::NONCE,
        );

        $headerB = $builder->buildWithBodyhash(
            self::CLIENT_ID,
            'secret-B',
            'GET',
            self::HOST,
            self::PORT,
            $resourcePath,
            $payload,
            self::TIMESTAMP,
            self::NONCE,
        );

        preg_match('/bodyhash="([^"]*)"/', $headerA, $matchA);
        preg_match('/bodyhash="([^"]*)"/', $headerB, $matchB);

        self::assertTrue(isset($matchA[1], $matchB[1]), 'bodyhash capture group missing');
        self::assertSame($matchA[1], $matchB[1], 'bodyhash must be plain SHA-256, independent of the secret.');

        preg_match('/mac="([^"]*)"/', $headerA, $macA);
        preg_match('/mac="([^"]*)"/', $headerB, $macB);

        self::assertTrue(isset($macA[1], $macB[1]), 'mac capture group missing');
        self::assertNotSame($macA[1], $macB[1], 'mac must differ when secrets differ.');
    }

    public function testBuildWithoutBodyhashProducesExpectedFourComponentHeader(): void
    {
        $builder = new AmexMacHeaderBuilder();

        $header = $builder->buildWithoutBodyhash(
            self::CLIENT_ID,
            self::SECRET,
            'HEAD',
            self::HOST,
            self::PORT,
            '/api/payments',
            self::TIMESTAMP,
            self::NONCE,
        );

        self::assertStringStartsWith('MAC ', $header);
        self::assertStringNotContainsString('bodyhash=', $header);

        preg_match_all('/(id|ts|nonce|mac)="([^"]*)"/', $header, $matches, \PREG_SET_ORDER);

        self::assertCount(4, $matches, 'HEAD header must have exactly 4 components.');
    }
}
