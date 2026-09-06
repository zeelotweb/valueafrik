<?php

namespace App\Jobs;

use App\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched with a delay equal to the ring duration when a call starts
 * ringing. If nobody has answered by the time this runs, it registers the
 * call as missed. A no-op if the call was already answered, declined, or
 * canceled in the meantime — see LiveSession::expireIfStale().
 */
class ExpireRingingCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LiveSession $session)
    {
    }

    public function handle(): void
    {
        $this->session->expireIfStale();
    }
}
