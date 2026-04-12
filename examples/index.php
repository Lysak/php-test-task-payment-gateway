<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Lysak\PhpTestTaskPaymentGateway\Config\MtlsConfig;
use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayConfig;
use Lysak\PhpTestTaskPaymentGateway\Config\PaymentGatewayEnv;
use Lysak\PhpTestTaskPaymentGateway\PaymentGatewayClient;
use Symfony\Component\HttpFoundation\Request;

require \dirname(__DIR__) . '/vendor/autoload.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(paymentGatewayExampleMain());
}

/**
 * Runnable BadSSL live check.
 *
 * Demonstrates how an integrator typically wires this package into their own
 * code: read `.env` with whatever their host framework/stack uses (Laravel's
 * `env()`, Symfony's `%env(...)%`, `vlucas/phpdotenv`, Docker env vars, etc.),
 * then construct PaymentGatewayConfig/MtlsConfig from plain strings, and inject
 * a configured PSR-18 client.
 *
 * For this standalone demo we use PHP-native `parse_ini_file` with
 * INI_SCANNER_RAW, which covers our minimal `.env` format without pulling any
 * runtime dependency.
 */
function paymentGatewayExampleMain(): int
{
    // --- Configuration ---------------------------------------------------
    $env = loadEnvFile(\dirname(__DIR__) . '/.env');
    $mtlsConfig = buildMtlsConfig($env);
    $config = buildGatewayConfig($env, $mtlsConfig);
    $endpointUrl = ($env[PaymentGatewayEnv::INTEGRATION_URL_ENV] ?? '') !== ''
        ? $env[PaymentGatewayEnv::INTEGRATION_URL_ENV]
        : 'https://client.badssl.com/';

    // --- Send request ----------------------------------------------------
    $gatewayClient = new PaymentGatewayClient(
        $config,
        new Client([...$mtlsConfig->toHttpClientOptions(), 'timeout' => 15]),
        new HttpFactory(),
    );

    $payload = [
        'transaction_id' => '12345',
        'amount' => '99.99',
        'currency' => 'USD',
    ];

    fwrite(\STDOUT, "Payment gateway live check\n");
    fwrite(\STDOUT, \sprintf("Endpoint: %s\n", $endpointUrl));
    fwrite(\STDOUT, \sprintf("Certificate: %s\n", $mtlsConfig->certificatePath()));
    fwrite(\STDOUT, \sprintf("Client ID: %s\n", $config->clientId()));

    $response = $gatewayClient->send(Request::METHOD_GET, $endpointUrl, $payload);

    // --- Output ----------------------------------------------------------
    fwrite(\STDOUT, \sprintf("HTTP %d\n", $response->getStatusCode()));
    fwrite(\STDOUT, \sprintf("Response: %s\n", $response->getBody()->getContents()));
    fwrite(\STDOUT, "mTLS flow completed successfully.\n");

    return 0;
}

// =========================================================================
//  Configuration helpers
// =========================================================================

/**
 * @return array<string, string>
 */
function loadEnvFile(string $envPath): array
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new RuntimeException(\sprintf('Cannot read env file: %s', $envPath));
    }

    $values = parse_ini_file($envPath, false, \INI_SCANNER_RAW);

    if ($values === false) {
        throw new RuntimeException(\sprintf('Failed to parse env file: %s', $envPath));
    }

    return $values;
}

/**
 * @param array<string, string> $env
 */
function buildMtlsConfig(array $env): MtlsConfig
{
    $envDirectory = \dirname(__DIR__);

    $certificatePath = resolveEnvPath($env[PaymentGatewayEnv::MTLS_CERT_PATH_ENV] ?? '', $envDirectory);

    if ($certificatePath === null) {
        throw new InvalidArgumentException(
            PaymentGatewayEnv::MTLS_CERT_PATH_ENV . ' must be configured in .env.',
        );
    }

    return new MtlsConfig(
        $certificatePath,
        resolveEnvPath($env[PaymentGatewayEnv::MTLS_KEY_PATH_ENV] ?? '', $envDirectory),
        ($env[PaymentGatewayEnv::MTLS_KEY_PASSPHRASE_ENV] ?? '') !== ''
            ? $env[PaymentGatewayEnv::MTLS_KEY_PASSPHRASE_ENV]
            : null,
    );
}

/**
 * @param array<string, string> $env
 */
function buildGatewayConfig(array $env, MtlsConfig $mtlsConfig): PaymentGatewayConfig
{
    $hmacSecret = $env[PaymentGatewayEnv::HMAC_SECRET_ENV] ?? '';

    if ($hmacSecret === '') {
        throw new InvalidArgumentException(
            PaymentGatewayEnv::HMAC_SECRET_ENV . ' must be configured in .env.',
        );
    }

    return new PaymentGatewayConfig(
        $env[PaymentGatewayEnv::PAYMENT_CLIENT_ID] ?? '',
        $hmacSecret,
        $mtlsConfig,
    );
}

function resolveEnvPath(string $path, string $base): ?string
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

    return $base . \DIRECTORY_SEPARATOR . $path;
}
