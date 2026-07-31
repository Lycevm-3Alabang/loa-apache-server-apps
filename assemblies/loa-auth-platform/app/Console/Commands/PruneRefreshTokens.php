<?php

namespace App\Console\Commands;

use App\Models\RefreshToken;
use Illuminate\Console\Command;

class PruneRefreshTokens extends Command
{
    protected $signature = 'refresh-tokens:prune';

    protected $description = 'Purge refresh token records that are expired or revoked for more than 30 days';

    public function handle(): int
    {
        $deleted = RefreshToken::where(function ($query) {
            $query->whereNotNull('revoked_at')
                ->where('revoked_at', '<', now()->subDays(30))
                ->orWhere('expires_at', '<', now()->subDays(30));
        })->delete();

        $this->info("Purged {$deleted} refresh token record(s).");

        return self::SUCCESS;
    }
}
