<?php

namespace App\Services;

class JWTService
{
    private string $secret;
    private string $algo = 'HS256';
    private int $accessTTL;
    private int $refreshTTL;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->accessTTL = config('jwt.access_ttl', 15);
        $this->refreshTTL = config('jwt.refresh_ttl', 10080);
    }

    public function generateAccessToken(array $claims): string
    {
        $payload = array_merge($claims, [
            'iat' => time(),
            'exp' => time() + ($this->accessTTL * 60),
            'type' => 'access',
        ]);

        return $this->encode($payload);
    }

    public function generateRefreshToken(array $claims): string
    {
        $payload = array_merge($claims, [
            'iat' => time(),
            'exp' => time() + ($this->refreshTTL * 60),
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ]);

        return $this->encode($payload);
    }

    public function validate(string $token): ?array
    {
        $payload = $this->decode($token);

        if ($payload === null) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public function getClaims(string $token): ?array
    {
        return $this->validate($token);
    }

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => $this->algo,
            'typ' => 'JWT',
        ]));

        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign("$header.$payloadEncoded");

        return "$header.$payloadEncoded.$signature";
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
