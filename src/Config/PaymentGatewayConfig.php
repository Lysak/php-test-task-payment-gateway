<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Config;

use InvalidArgumentException;

class PaymentGatewayConfig
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $hmacSecret,
        private readonly ?MtlsConfig $mtls = null,
    ) {
        if ($this->clientId === '') {
            throw new InvalidArgumentException('The Client ID must not be empty.');
        }

        if ($this->hmacSecret === '') {
            throw new InvalidArgumentException('The HMAC secret must not be empty.');
        }
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function hmacSecret(): string
    {
        return $this->hmacSecret;
    }

    public function mtls(): ?MtlsConfig
    {
        return $this->mtls;
    }
}
