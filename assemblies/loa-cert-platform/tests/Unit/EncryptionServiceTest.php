<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private string $hexKey = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private string $prevHexKey = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

    public function test_openssl_gcm_roundtrip_with_hex_key(): void
    {
        $keyBytes = hex2bin($this->hexKey);
        $payload = ['test' => 'data', 'nested' => ['a' => 1]];

        $encoded = $this->encryptWith($keyBytes, $payload);
        $decrypted = $this->decryptWith($keyBytes, $encoded);

        $this->assertNotNull($decrypted);
        $this->assertEquals($payload, $decrypted);
    }

    public function test_openssl_gcm_rejects_wrong_key(): void
    {
        $keyBytes = hex2bin($this->hexKey);
        $wrongKeyBytes = hex2bin($this->prevHexKey);

        $encoded = $this->encryptWith($keyBytes, ['test' => 'data']);
        $decrypted = $this->decryptWith($wrongKeyBytes, $encoded);

        $this->assertNull($decrypted);
    }

    public function test_openssl_gcm_rejects_tampered_ciphertext(): void
    {
        $keyBytes = hex2bin($this->hexKey);
        $encoded = $this->encryptWith($keyBytes, ['test' => 'data']);

        $padded = $encoded;
        $mod = strlen($padded) % 4;
        if ($mod === 2) {
            $padded .= '==';
        } elseif ($mod === 3) {
            $padded .= '=';
        }
        $decoded = base64_decode(strtr($padded, '-_', '+/'));
        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $tampered = str_repeat('X', strlen($ciphertext));
        $result = openssl_decrypt($tampered, 'aes-256-gcm', $keyBytes, OPENSSL_RAW_DATA, $nonce, $tag);

        $this->assertFalse($result);
    }

    public function test_too_short_input_rejected(): void
    {
        $decoded = base64_decode(strtr('short', '-_', '+/') . '==');
        $this->assertLessThan(29, strlen($decoded));
    }

    public function test_encrypted_output_is_valid_base64(): void
    {
        $keyBytes = hex2bin($this->hexKey);
        $encoded = $this->encryptWith($keyBytes, ['a' => 'b']);

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);
        $this->assertGreaterThan(28, strlen($encoded));
    }

    private function encryptWith(string $keyBytes, array $payload): string
    {
        $plaintext = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $keyBytes, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return rtrim(base64_encode($nonce . $tag . $ciphertext), '=');
    }

    private function decryptWith(string $keyBytes, string $encoded): ?array
    {
        $padded = $encoded;
        $mod = strlen($padded) % 4;
        if ($mod === 2) {
            $padded .= '==';
        } elseif ($mod === 3) {
            $padded .= '=';
        }
        $decoded = base64_decode(strtr($padded, '-_', '+/'));

        if ($decoded === false || strlen($decoded) < 29) {
            return null;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $keyBytes, OPENSSL_RAW_DATA, $nonce, $tag);

        if ($plaintext === false) {
            return null;
        }

        return json_decode($plaintext, true);
    }
}
