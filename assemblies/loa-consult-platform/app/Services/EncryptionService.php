<?php

namespace App\Services;

class EncryptionService
{
    private ?string $key;
    private ?string $previousKey;

    public function __construct()
    {
        $rawKey = config('auth-platform.encryption_key', '');
        $this->key = $rawKey !== '' ? $this->decodeKey($rawKey) : null;

        $rawPrevious = config('auth-platform.encryption_key_previous', '');
        $this->previousKey = $rawPrevious !== '' ? $this->decodeKey($rawPrevious) : null;
    }

    public function isConfigured(): bool
    {
        return $this->key !== null;
    }

    /**
     * Decrypt an AES-256-GCM SSO payload (nonce[12] + tag[16] + ciphertext).
     * Tries current key, then previous key on failure.
     */
    public function decrypt(string $encoded): ?array
    {
        if ($this->key === null) {
            return null;
        }

        $encoded = strtr($encoded, '-_', '+/');

        $pad = strlen($encoded) % 4;

        if ($pad > 0) {
            $encoded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strlen($decoded) < 29) {
            return null;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );

        if ($plaintext === false && $this->previousKey !== null) {
            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $this->previousKey,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
            );
        }

        if ($plaintext === false) {
            return null;
        }

        $payload = json_decode($plaintext, true);

        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    private function decodeKey(string $rawKey): string
    {
        if (str_starts_with($rawKey, 'base64:')) {
            $key = base64_decode(substr($rawKey, 7), true);

            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('ENCRYPTION_KEY base64 value must decode to 32 bytes');
            }

            return $key;
        }

        $key = hex2bin($rawKey);

        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('ENCRYPTION_KEY must be a 64-character hex string (32 bytes) or base64: prefixed');
        }

        return $key;
    }
}
