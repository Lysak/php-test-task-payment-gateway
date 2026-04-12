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

## Certificates

Place your mTLS client certificates in the `certs/` directory. Two options:

- **Combined file** (key + cert in one `.pem`): set only `PAYMENT_MTLS_CERT_PATH`, leave `PAYMENT_MTLS_KEY_PATH` empty.
- **Separate files** (`.crt` + `.key`): set both `PAYMENT_MTLS_CERT_PATH` and `PAYMENT_MTLS_KEY_PATH`.

For BadSSL testing, download the combined `.pem` from [badssl.com/download](https://badssl.com/download/) (passphrase: `badssl.com`) and place it at `certs/badssl.com-client.pem`.

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
