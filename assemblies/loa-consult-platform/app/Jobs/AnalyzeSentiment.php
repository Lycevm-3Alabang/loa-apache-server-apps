<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Placeholder sentiment pipeline (module spec §2 comments).
 *
 * Real queue job shape: dispatched fire-and-forget after comment writes,
 * best-effort, caller swallows errors. Analysis itself is a placeholder —
 * sentiment columns stay null until the reporting work lands (C4 averages
 * are null-safe).
 */
class AnalyzeSentiment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int|string $commentId,
        public readonly string $comment,
    ) {}

    public function handle(): void
    {
        // Placeholder — no analysis yet.
    }
}
