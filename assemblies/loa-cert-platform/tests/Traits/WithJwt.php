<?php

namespace Tests\Traits;

use App\Services\JWTService;

trait WithJwt
{
    private function createJwtToken(array $overrides = []): string
    {
        $secret = config('jwt.secret', 'dev-only-secret-change-before-production');

        $claims = array_merge([
            'iss' => 'loa-auth',
            'aud' => 'loa-cert',
            'iat' => time(),
            'exp' => time() + 900,
            'type' => 'access',
            'sub' => '00000000-0000-0000-0000-000000000001',
            'email' => 'admin@lyceumalabang.edu.ph',
            'name' => 'Admin User',
            'tenant' => [
                'id' => '00000000-0000-0000-0000-000000000001',
                'slug' => config('cert-platform.tenant_slug', 'loa'),
            ],
            'permissions' => [
                'admin:/api/v1/events',
                'admin:/api/v1/events/{id}',
                'admin:/api/v1/events/{id}/stats',
                'admin:/api/v1/events/{id}/clone-template',
                'admin:/api/v1/events/{id}/clone-email-template',
                'admin:/api/v1/events/{id}/bulk-issue',
                'admin:/api/v1/events/{id}/reissue',
                'admin:/api/v1/events/{id}/revoke-expired',
                'admin:/api/v1/events/{id}/issue-completed',
                'admin:/api/v1/events/{id}/attendees',
                'admin:/api/v1/events/{id}/attendees/import',
                'admin:/api/v1/attendees/{id}',
                'admin:/api/v1/attendees/{id}/with-cert',
                'admin:/api/v1/attendees/{id}/delete-preview',
                'admin:/api/v1/attendees/{id}/file-data',
                'admin:/api/v1/templates',
                'admin:/api/v1/templates/{id}',
                'admin:/api/v1/certificates',
                'admin:/api/v1/certificates/bulk',
                'admin:/api/v1/certificates/upload',
                'admin:/api/v1/certificates/{id}',
                'admin:/api/v1/certificates/{id}/pdf',
                'admin:/api/v1/certificates/{id}/download',
                'admin:/api/v1/certificates/{id}/revoke',
                'admin:/api/v1/certificates/{id}/email',
                'admin:/api/v1/certificates/{id}/email-logs',
                'admin:/api/v1/certificates/{id}/reissue',
                'admin:/api/v1/certificates/expire',
                'admin:/api/v1/certificates/qr',
            ],
        ], $overrides);

        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($claims));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $secret, true)
        );

        return "$header.$payload.$signature";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function actingAsJwt(): self
    {
        $token = $this->createJwtToken();
        $this->withHeader('Authorization', "Bearer $token");

        return $this;
    }
}
