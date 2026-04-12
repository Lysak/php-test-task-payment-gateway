# php-test-task-payment-gateway

PHP Composer package that sends HMAC-signed GET requests over mTLS. Implements the [American Express HMAC](https://developer.americanexpress.com/documentation/api-security/hmac) authorization scheme.

## Setup

```bash
composer install
cp .env.example .env
```

## .env parameters

| Variable | Required | Description |
|---|---|---|
| `PAYMENT_HMAC_SECRET` | yes | Shared secret for HMAC-SHA256 signing |
| `PAYMENT_CLIENT_ID` | yes | Client ID (Amex MAC `id` field) |
| `PAYMENT_MTLS_CERT_PATH` | yes | Path to client certificate (.pem / .crt) |
| `PAYMENT_MTLS_KEY_PATH` | no | Path to private key (omit if bundled with cert) |
| `PAYMENT_MTLS_KEY_PASSPHRASE` | no | Private key passphrase |
| `PAYMENT_INTEGRATION_URL` | yes | API endpoint for integration test |

For BadSSL testing, download certificates from [badssl.com/download](https://badssl.com/download/) (passphrase: `badssl.com`).

## Tests

```bash
composer test                                    # full suite
./vendor/bin/phpunit --testsuite=unit            # unit only
./vendor/bin/phpunit --testsuite=integration     # live BadSSL (requires .env)
```

The integration test self-skips when `.env` is missing or the endpoint is unreachable.

## Quality

```bash
composer quality    # cs-check + phpstan + phpmd + rector (dry-run)
composer cs-fix     # auto-fix code style
```
