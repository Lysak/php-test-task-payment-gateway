<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Lysak\PhpTestTaskPaymentGateway\Config\MtlsConfig;
use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayConfig;
use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayEnv;
use Lysak\PhpTestTaskPaymentGateway\PaymentGatewayClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Live mTLS check against client.badssl.com.
 *
 * Per TestTask.md: "at least one integration test that sends a real HTTP
 * request using mTLS and validates the response (provided the tester fills
 * in real paths/secrets in .env)." An in-memory PSR-18 fake cannot fulfil
 * this requirement — it never performs a TLS handshake, never presents a
 * client certificate, and never validates the server certificate. Only a
 * real HTTP client hitting a real mTLS endpoint proves the wiring works.
 *
 * The test self-skips when the required env values are absent so the suite
 * remains runnable on CI machines without credentials.
 */
#[CoversClass(PaymentGatewayClient::class)]
class BadSslMtlsIntegrationTest extends TestCase
{
    public function testItPerformsRealMtlsRequestAgainstBadSsl(): void
    {
        $hmacSecret = $this->env(PaymentGatewayEnv::HMAC_SECRET_ENV);
        $clientId = $this->env(PaymentGatewayEnv::PAYMENT_CLIENT_ID);
        $certificatePath = $this->resolvePath($this->env(PaymentGatewayEnv::MTLS_CERT_PATH_ENV));
        $keyPath = $this->resolvePath($this->env(PaymentGatewayEnv::MTLS_KEY_PATH_ENV));
        $keyPassphrase = $this->env(PaymentGatewayEnv::MTLS_KEY_PASSPHRASE_ENV);
        $endpointUrl = $this->env(PaymentGatewayEnv::INTEGRATION_URL_ENV);

        if ($hmacSecret === '' || $clientId === '' || $certificatePath === null || $endpointUrl === '') {
            self::markTestSkipped(
                'Integration credentials are not configured in .env.testing. '
                . 'Set ' . PaymentGatewayEnv::MTLS_CERT_PATH_ENV . ', '
                . PaymentGatewayEnv::HMAC_SECRET_ENV . ', '
                . PaymentGatewayEnv::PAYMENT_CLIENT_ID . ' and '
                . PaymentGatewayEnv::INTEGRATION_URL_ENV . ' to run this test.',
            );
        }

        $mtlsConfig = new MtlsConfig(
            $certificatePath,
            $keyPath,
            $keyPassphrase !== '' ? $keyPassphrase : null,
        );

        $gatewayClient = new PaymentGatewayClient(
            new PaymentGatewayConfig($clientId, $hmacSecret, $mtlsConfig),
            new Client([...$mtlsConfig->toHttpClientOptions(), 'timeout' => 15]),
            new HttpFactory(),
        );

        $response = $gatewayClient->send(Request::METHOD_GET, $endpointUrl, [
            'transaction_id' => '12345',
            'amount' => '99.99',
            'currency' => 'USD',
        ]);

        self::assertGreaterThanOrEqual(200, $response->getStatusCode());
        self::assertLessThan(300, $response->getStatusCode());
    }

    private function env(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return \is_string($value) ? $value : '';
    }

    private function resolvePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . $path;
    }
}
