<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Config;

use InvalidArgumentException;

class MtlsConfig
{
    public function __construct(
        private readonly string $certificatePath,
        private readonly ?string $privateKeyPath = null,
        private readonly ?string $privateKeyPassphrase = null,
        private readonly ?string $caBundlePath = null,
    ) {
        $this->assertReadableFilePath(
            $this->certificatePath,
            'The mTLS certificate path must point to a readable file.',
        );

        if ($this->privateKeyPath !== null) {
            $this->assertReadableFilePath(
                $this->privateKeyPath,
                'The mTLS private key path must point to a readable file.',
            );
        }

        if ($this->caBundlePath !== null) {
            $this->assertReadableFilePath(
                $this->caBundlePath,
                'The TLS CA bundle path must point to a readable file.',
            );
        }

        if ($this->privateKeyPassphrase === '') {
            throw new InvalidArgumentException('The mTLS private key passphrase must not be empty when provided.');
        }
    }

    public function certificatePath(): string
    {
        return $this->certificatePath;
    }

    public function privateKeyPath(): ?string
    {
        return $this->privateKeyPath;
    }

    public function privateKeyPassphrase(): ?string
    {
        return $this->privateKeyPassphrase;
    }

    public function caBundlePath(): ?string
    {
        return $this->caBundlePath;
    }

    /**
     * Build an array of TLS-related client options derived from this mTLS configuration.
     *
     * The returned keys (`cert`, `ssl_key`, `verify`) follow the conventions
     * used by Guzzle / cURL, but the method itself has no dependency on any
     * concrete HTTP client — it simply returns a plain array that the caller
     * can spread into their client constructor.
     *
     * @return array{
     *     cert: array{0: string, 1: string}|string,
     *     ssl_key?: array{0: string, 1: string}|string,
     *     verify: string|true
     * }
     */
    public function toHttpClientOptions(): array
    {
        $options = [
            'cert' => $this->privateKeyPath !== null || $this->privateKeyPassphrase === null
                ? $this->certificatePath
                : [$this->certificatePath, $this->privateKeyPassphrase],
            'verify' => $this->caBundlePath ?? true,
        ];

        if ($this->privateKeyPath !== null) {
            $options['ssl_key'] = $this->privateKeyPassphrase === null
                ? $this->privateKeyPath
                : [$this->privateKeyPath, $this->privateKeyPassphrase];
        }

        return $options;
    }

    private function assertReadableFilePath(string $path, string $message): void
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException($message);
        }
    }
}
