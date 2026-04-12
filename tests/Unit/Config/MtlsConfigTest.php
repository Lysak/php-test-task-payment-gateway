<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Tests\Unit\Config;

use InvalidArgumentException;
use Lysak\PhpTestTaskPaymentGateway\Config\MtlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MtlsConfig::class)]
class MtlsConfigTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->tempFiles = [];
    }

    public function testItAcceptsReadableCertificatePath(): void
    {
        $certPath = $this->createTempFile('cert');

        $config = new MtlsConfig($certPath);

        self::assertSame($certPath, $config->certificatePath());
        self::assertNull($config->privateKeyPath());
        self::assertNull($config->privateKeyPassphrase());
        self::assertNull($config->caBundlePath());
    }

    public function testItAcceptsFullMtlsMaterial(): void
    {
        $certPath = $this->createTempFile('cert');
        $keyPath = $this->createTempFile('key');
        $caPath = $this->createTempFile('ca');

        $config = new MtlsConfig($certPath, $keyPath, 'passphrase', $caPath);

        self::assertSame($keyPath, $config->privateKeyPath());
        self::assertSame('passphrase', $config->privateKeyPassphrase());
        self::assertSame($caPath, $config->caBundlePath());
    }

    public function testItRejectsMissingCertificateFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mTLS certificate path must point to a readable file');

        new MtlsConfig('/definitely/does/not/exist.pem');
    }

    public function testItRejectsEmptyCertificatePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mTLS certificate path must point to a readable file');

        new MtlsConfig('');
    }

    public function testItRejectsMissingPrivateKeyFileWhenProvided(): void
    {
        $certPath = $this->createTempFile('cert');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mTLS private key path must point to a readable file');

        new MtlsConfig($certPath, '/definitely/does/not/exist.key');
    }

    public function testItRejectsMissingCaBundleFileWhenProvided(): void
    {
        $certPath = $this->createTempFile('cert');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TLS CA bundle path must point to a readable file');

        new MtlsConfig($certPath, null, null, '/definitely/does/not/exist.ca');
    }

    public function testItRejectsEmptyPassphrase(): void
    {
        $certPath = $this->createTempFile('cert');

        // Subtle edge: null passphrase is valid (unencrypted key), but an empty
        // string is almost always a config mistake — a missing env var silently
        // collapsing to ''. Fail fast on construction instead of at handshake.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mTLS private key passphrase must not be empty when provided');

        new MtlsConfig($certPath, null, '');
    }

    private function createTempFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mtls-' . $prefix . '-');

        if ($path === false) {
            self::fail('Unable to create temp fixture file.');
        }

        $this->tempFiles[] = $path;

        return $path;
    }
}
