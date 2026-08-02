<?php

namespace Tests\Traits;

trait RefreshJwtSecret
{
    protected function setJwtSecret(?string $secret = null): void
    {
        config(['jwt.secret' => $secret ?? 'test-secret-key-for-testing-only-32chars']);
    }
}
