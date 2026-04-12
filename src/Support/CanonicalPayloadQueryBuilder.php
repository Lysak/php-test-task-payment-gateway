<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Support;

use InvalidArgumentException;

class CanonicalPayloadQueryBuilder
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function build(array $payload): string
    {
        $normalizedPayload = [];

        foreach ($payload as $key => $value) {
            if (\is_array($value) || \is_object($value)) {
                throw new InvalidArgumentException(
                    'Payload values must be scalar or null.',
                );
            }

            $normalizedPayload[(string) $key] = (string) $value;
        }

        ksort($normalizedPayload);

        return http_build_query(
            $normalizedPayload,
            '',
            '&',
            \PHP_QUERY_RFC3986,
        );
    }
}
