<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Security;

use Lysak\PhpTestTaskPaymentGateway\Security\HmacSignatureGenerator;
use Lysak\PhpTestTaskPaymentGateway\Support\CanonicalPayloadQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HmacSignatureGenerator::class)]
class HmacSignatureGeneratorTest extends TestCase
{
    private const string FIXED_SECRET = 'secret';
    private const string FIXED_PAYLOAD = 'amount=99.99&currency=USD&transaction_id=12345';

    public function testItGeneratesStableBase64EncodedSha256Hmac(): void
    {
        $generator = new HmacSignatureGenerator();

        $actual = $generator->generate(self::FIXED_PAYLOAD, self::FIXED_SECRET);

        $expected = base64_encode(
            hash_hmac('sha256', self::FIXED_PAYLOAD, self::FIXED_SECRET, true),
        );

        self::assertSame($expected, $actual);
    }

    public function testItProducesIdenticalSignaturesForCanonicallyEquivalentPayloads(): void
    {
        $generator = new HmacSignatureGenerator();
        $queryBuilder = new CanonicalPayloadQueryBuilder();

        $unordered = $queryBuilder->build([
            'transaction_id' => '12345',
            'currency' => 'USD',
            'amount' => '99.99',
        ]);
        $ordered = $queryBuilder->build([
            'amount' => '99.99',
            'currency' => 'USD',
            'transaction_id' => '12345',
        ]);

        self::assertSame(self::FIXED_PAYLOAD, $ordered);
        self::assertSame(
            $generator->generate($ordered, self::FIXED_SECRET),
            $generator->generate($unordered, self::FIXED_SECRET),
        );
    }

    public function testItGeneratesSignatureForEmptyPayload(): void
    {
        $generator = new HmacSignatureGenerator();

        $actual = $generator->generate('', self::FIXED_SECRET);

        hash_hmac('sha256', '', self::FIXED_SECRET, true)
            |> base64_encode(...)
            |> (fn ($x) => self::assertSame($x, $actual));
    }

    public function testSignatureChangesWhenSecretChanges(): void
    {
        $generator = new HmacSignatureGenerator();

        $first = $generator->generate(self::FIXED_PAYLOAD, self::FIXED_SECRET);
        $second = $generator->generate(self::FIXED_PAYLOAD, 'different-secret');

        self::assertNotSame($first, $second);
    }
}
