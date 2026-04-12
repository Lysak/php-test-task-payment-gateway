<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Security;

use Lysak\PhpTestTaskPaymentGateway\Security\NonceGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NonceGenerator::class)]
class NonceGeneratorTest extends TestCase
{
    /**
     * RFC 4122 UUID v4 format:
     *   - third group starts with "4"   (version nibble)
     *   - fourth group starts with 8/9/a/b (variant 10xx bits)
     */
    private const string UUID_V4_REGEX =
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testItGeneratesRfc4122CompliantUuidV4(): void
    {
        $nonce = (new NonceGenerator())->generate();

        self::assertMatchesRegularExpression(self::UUID_V4_REGEX, $nonce);
    }

    public function testConsecutiveCallsProduceDifferentValues(): void
    {
        // Amex HMAC spec requires nonces be unique per request. If a refactor
        // ever introduces caching / static state here, this collapses to a
        // catastrophic replay vulnerability — this test catches it instantly.
        $generator = new NonceGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }

    public function testItGeneratesUniqueValuesAtBatchScale(): void
    {
        $generator = new NonceGenerator();
        $batch = [];

        for ($i = 0; $i < 1000; ++$i) {
            $batch[] = $generator->generate();
        }

        self::assertCount(1000, array_unique($batch), 'Nonce collision detected within 1000 samples.');
    }
}
