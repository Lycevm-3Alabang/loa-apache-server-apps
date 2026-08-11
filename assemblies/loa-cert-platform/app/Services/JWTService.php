<?php

namespace App\Services;

class JWTService
{
    private string $secret;
    private string $algo = 'HS256';

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? config('jwt.secret');
    }

    /**
     * Validate a JWT access token. Returns claims on success, null on failure.
     * Cert does NOT sign tokens — validate only.
     */
    public function validate(string $token): ?array
    {
        $payload = $this->decode($token);

        if ($payload === null) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'access') {
            return null;
        }

        return $payload;
    }

    private function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSignature = $this->sign("$header.$payload");

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadData = json_decode($this->base64UrlDecode($payload), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $payloadData;
    }

    private function sign(string $data): string
    {
        $signature = hash_hmac('sha256', $data, $this->secret, true);
        return $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
