<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Security;

class HmacSignatureGenerator
{
    private const string ALGORITHM = 'sha256';

    /**
     * Generate a raw HMAC-SHA256 hash and return it Base64-encoded.
     *
     * American Express HMAC spec requires Base64 encoding for both
     * bodyhash and mac values in the Authorization header.
     */
    public function generate(string $content, string $secret): string
    {
        return base64_encode(
            hash_hmac(self::ALGORITHM, $content, $secret, true),
        );
    }
}
