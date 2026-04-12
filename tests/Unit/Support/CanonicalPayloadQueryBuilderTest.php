<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Support;

use InvalidArgumentException;
use Lysak\PhpTestTaskPaymentGateway\Support\CanonicalPayloadQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CanonicalPayloadQueryBuilder::class)]
class CanonicalPayloadQueryBuilderTest extends TestCase
{
    private CanonicalPayloadQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CanonicalPayloadQueryBuilder();
    }

    public function testItSortsKeysAlphabeticallyRegardlessOfInputOrder(): void
    {
        $actual = $this->builder->build([
            'transaction_id' => '12345',
            'currency' => 'USD',
            'amount' => '99.99',
        ]);

        self::assertSame('amount=99.99&currency=USD&transaction_id=12345', $actual);
    }

    public function testItEncodesSpacesUsingRfc3986PercentTwentyNotPlus(): void
    {
        $actual = $this->builder->build(['note' => 'hello world']);

        // RFC3986 (the spec for URL components) uses %20 for space.
        // http_build_query's default (PHP_QUERY_RFC1738) uses '+', which would
        // silently desync signature and URL. This test is the load-bearing guard
        // for that invariant.
        self::assertSame('note=hello%20world', $actual);
        self::assertStringNotContainsString('+', $actual);
    }

    public function testItPercentEncodesSpecialCharactersInBothKeysAndValues(): void
    {
        $actual = $this->builder->build([
            'a b' => 'c&d',
            'redirect' => 'https://example.com/callback?x=1',
        ]);

        // Keys are sorted, both keys and values are percent-encoded.
        self::assertSame(
            'a%20b=c%26d&redirect=https%3A%2F%2Fexample.com%2Fcallback%3Fx%3D1',
            $actual,
        );
    }

    public function testItStringifiesScalarValuesDeterministically(): void
    {
        $actual = $this->builder->build([
            'attempts' => 3,
            'fee' => 1.5,
            'paid' => true,
            'refunded' => false,
        ]);

        // Contract: every scalar is funneled through (string) cast so the
        // resulting query string is byte-exact across PHP versions.
        // int 3   → "3"
        // float 1.5 → "1.5"
        // true    → "1"
        // false   → ""  (PHP's (string) cast of false is the empty string)
        self::assertSame('attempts=3&fee=1.5&paid=1&refunded=', $actual);
    }

    public function testItStringifiesNullValueToEmptyString(): void
    {
        $actual = $this->builder->build(['optional' => null]);

        // (string) null === ''. Documenting the behavior so a future refactor
        // can't silently drop null keys or crash on them.
        self::assertSame('optional=', $actual);
    }

    public function testEmptyPayloadProducesEmptyString(): void
    {
        self::assertSame('', $this->builder->build([]));
    }

    public function testItRejectsNestedArrayValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload values must be scalar or null.');

        $this->builder->build(['items' => ['a', 'b']]);
    }

    public function testItRejectsObjectValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload values must be scalar or null.');

        $this->builder->build(['meta' => new stdClass()]);
    }

    public function testCanonicalOutputIsByteIdenticalAcrossEquivalentInputs(): void
    {
        // Different input orderings of the same map MUST produce the same
        // string — this is THE invariant the HMAC relies on.
        $first = $this->builder->build([
            'b' => '2',
            'a' => '1',
            'c' => '3',
        ]);
        $second = $this->builder->build([
            'a' => '1',
            'c' => '3',
            'b' => '2',
        ]);

        self::assertSame($first, $second);
        self::assertSame('a=1&b=2&c=3', $first);
    }
}
