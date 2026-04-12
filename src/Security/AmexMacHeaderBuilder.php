<?php

declare(strict_types=1);

namespace Lysak\PhpTestTaskPaymentGateway\Security;

/**
 * Builds an American Express HMAC Authorization header.
 *
 * HEAD (4 components):
 *   MAC id="...",ts="...",nonce="...",mac="..."
 *
 * GET, DELETE, POST, PUT (5 components):
 *   MAC id="...",ts="...",nonce="...",bodyhash="...",mac="..."
 *
 * @see https://developer.americanexpress.com/documentation/api-security/hmac
 */
class AmexMacHeaderBuilder
{
    /**
     * mac base string: ts\nnonce\nhttpMethod\nresourcePath\nhost\nport\nbodyhash\n
     * For HEAD the bodyhash line is omitted.
     */
    private const string SIGNATURE_FORMAT_WITH_BODYHASH = "%s\n%s\n%s\n%s\n%s\n%s\n%s\n";
    private const string SIGNATURE_FORMAT_WITHOUT_BODYHASH = "%s\n%s\n%s\n%s\n%s\n%s\n";

    private const string AUTH_HEADER_WITH_BODYHASH = 'MAC id="%s",ts="%s",nonce="%s",bodyhash="%s",mac="%s"';
    private const string AUTH_HEADER_WITHOUT_BODYHASH = 'MAC id="%s",ts="%s",nonce="%s",mac="%s"';

    public function __construct(
        private readonly HmacSignatureGenerator $signatureGenerator = new HmacSignatureGenerator(),
    ) {
    }

    /**
     * Build a full MAC Authorization header value (with bodyhash).
     *
     * Used for POST, PUT, GET, DELETE where a payload/body is present.
     *
     * @param string $clientId    Application's Client ID (MAC id)
     * @param string $secret      Application's Client Secret (HMAC key)
     * @param string $httpMethod  HTTP verb (GET, POST, PUT, DELETE)
     * @param string $host        Hostname of the API endpoint
     * @param int    $port        Port number (443 for HTTPS)
     * @param string $resourcePath URI path + query (e.g. /v1/payments?amount=100)
     * @param string $payload     Raw body (JSON) or canonical query string
     * @param string $timestamp   Unix epoch in milliseconds
     * @param string $nonce       Unique identifier for this request
     */
    public function buildWithBodyhash(
        string $clientId,
        string $secret,
        string $httpMethod,
        string $host,
        int $port,
        string $resourcePath,
        string $payload,
        string $timestamp,
        string $nonce,
    ): string {
        $bodyhash = base64_encode(hash('sha256', $payload, true));

        $macInput = \sprintf(
            self::SIGNATURE_FORMAT_WITH_BODYHASH,
            $timestamp,
            $nonce,
            $httpMethod,
            $resourcePath,
            $host,
            $port,
            $bodyhash,
        );

        $mac = $this->signatureGenerator->generate($macInput, $secret);

        return \sprintf(
            self::AUTH_HEADER_WITH_BODYHASH,
            $clientId,
            $timestamp,
            $nonce,
            $bodyhash,
            $mac,
        );
    }

    /**
     * Build a MAC Authorization header value without bodyhash.
     *
     * Used for HEAD requests (4 components only).
     *
     * @param string $clientId    Application's Client ID (MAC id)
     * @param string $secret      Application's Client Secret (HMAC key)
     * @param string $httpMethod  HTTP verb (HEAD)
     * @param string $host        Hostname of the API endpoint
     * @param int    $port        Port number (443 for HTTPS)
     * @param string $resourcePath URI path (e.g. /v1/status)
     * @param string $timestamp   Unix epoch in milliseconds
     * @param string $nonce       Unique identifier for this request
     */
    public function buildWithoutBodyhash(
        string $clientId,
        string $secret,
        string $httpMethod,
        string $host,
        int $port,
        string $resourcePath,
        string $timestamp,
        string $nonce,
    ): string {
        $macInput = \sprintf(
            self::SIGNATURE_FORMAT_WITHOUT_BODYHASH,
            $timestamp,
            $nonce,
            $httpMethod,
            $resourcePath,
            $host,
            $port,
        );

        $mac = $this->signatureGenerator->generate($macInput, $secret);

        return \sprintf(
            self::AUTH_HEADER_WITHOUT_BODYHASH,
            $clientId,
            $timestamp,
            $nonce,
            $mac,
        );
    }
}
