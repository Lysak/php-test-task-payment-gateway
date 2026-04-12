<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Config;

class PaymentGatewayEnv
{
    public const string HMAC_SECRET_ENV = 'PAYMENT_HMAC_SECRET';
    public const string INTEGRATION_URL_ENV = 'PAYMENT_INTEGRATION_URL';
    public const string MTLS_CERT_PATH_ENV = 'PAYMENT_MTLS_CERT_PATH';
    public const string MTLS_KEY_PASSPHRASE_ENV = 'PAYMENT_MTLS_KEY_PASSPHRASE';
    public const string MTLS_KEY_PATH_ENV = 'PAYMENT_MTLS_KEY_PATH';
    public const string PAYMENT_CLIENT_ID = 'PAYMENT_CLIENT_ID';

    private function __construct()
    {
    }
}
