<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Security;

class NonceGenerator
{
    /**
     * Generate a UUID v4 nonce.
     * Must be unique for each request per Amex HMAC spec.
     */
    public function generate(): string
    {
        $bytes = random_bytes(16);

        // Set version 4 (0100) and variant 10xx bits per RFC 4122
        $bytes[6] = \chr(\ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = \chr(\ord($bytes[8]) & 0x3F | 0x80);

        return \sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }
}
