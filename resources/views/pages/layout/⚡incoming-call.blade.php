<?php

use App\Models\LiveSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Mounted once, globally, in the app shell — a call can arrive no matter
 * what page someone's on. Two ways it finds an incoming call:
 *
 *   1. A live broadcast while this component is already on screen.
 *   2. A DB check on mount, which is what makes "I just came online while
 *      it was still ringing" work — the row was there the whole time, we
 *      just hadn't loaded a page to notice it.
 */
new class extends Component {
    public ?int $ringingSessionId = null;

    public function mount(): void
    {
        $ringing = LiveSession::query()
            ->where('type', LiveSession::TYPE_CALL)
            ->where('callee_id', Auth::id())
            ->where('status', LiveSession::STATUS_RINGING)
            ->latest('started_at')
            ->first();

        if ($ringing?->isStillRinging()) {
            $this->ringingSessionId = $ringing->id;
        } elseif ($ringing) {
            $ringing->expireIfStale();
        }
    }

    #[Computed]
    public function call(): ?LiveSession
    {
        if (! $this->ringingSessionId) {
            return null;
        }

        // ringingSessionId is a plain client-visible property — the backstop
        // here is the same as culture-sprint's: whatever set it, only the
        // real callee of an actual call ever gets it back.
        $session = LiveSession::with('host.profile')->find($this->ringingSessionId);

        if (! $session || $session->type !== LiveSession::TYPE_CALL || $session->callee_id !== Auth::id()) {
            return null;
        }

        return $session;
    }

    #[Computed]
    public function ringDeadline(): ?string
    {
        return $this->call?->started_at->addSeconds((int) config('calls.ring_seconds'))->toIso8601String();
    }

    public function respond(bool $accept): void
    {
        $session = $this->call;

        if (! $session) {
            return;
        }

        $session->respondToRing(Auth::user(), $accept);

        $this->ringingSessionId = null;
        unset($this->call, $this->ringDeadline);

        if ($accept) {
            $this->redirect(route('live.show', $session), navigate: true);
        }
    }

    /**
     * Client-side fallback for when the deadline passes but no broadcast
     * has arrived yet (a dropped socket, broadcasting misconfigured). Just
     * re-checks the authoritative expiry — idempotent, never overrides an
     * answer/decline that already happened.
     */
    public function checkExpiry(): void
    {
        $call = $this->call;
        $call?->expireIfStale();

        if (! $call?->isStillRinging()) {
            $this->ringingSessionId = null;
            unset($this->call, $this->ringDeadline);
        }
    }

    public function getListeners(): array
    {
        return Auth::check()
            ? ['echo-private:App.Models.User.'.Auth::id().',.CallStatusUpdated' => 'onCallStatusUpdated']
            : [];
    }

    public function onCallStatusUpdated(array $event): void
    {
        if (($event['type'] ?? null) !== LiveSession::TYPE_CALL) {
            return;
        }

        $incomingId = (int) ($event['session_id'] ?? 0);

        if (! $incomingId) {
            return;
        }

        // This listener is a public Livewire method, reachable directly with
        // a forged payload — never trust $event['callee']['id'] on its own.
        // Re-verify who the callee actually is against the database.
        if (($event['status'] ?? null) === LiveSession::STATUS_RINGING) {
            $session = LiveSession::find($incomingId);

            if ($session && $session->type === LiveSession::TYPE_CALL && $session->callee_id === Auth::id()) {
                $this->ringingSessionId = $incomingId;
            }

            return;
        }

        if ($this->ringingSessionId && $incomingId === $this->ringingSessionId) {
            $this->ringingSessionId = null;
        }
    }
}; ?>

<div>
    @if ($this->call)
        <div
            x-data="{ deadline: @js($this->ringDeadline) }"
            x-init="setTimeout(() => $wire.$call('checkExpiry'), Math.max(0, new Date(deadline) - new Date()) + 500)"
            class="fixed inset-x-0 bottom-4 z-50 mx-auto flex w-[calc(100%-2rem)] max-w-sm items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 shadow-xl dark:border-stone-800 dark:bg-stone-900"
            data-test="incoming-call-ringer"
        >
            <a href="{{ route('profile.show', $this->call->host) }}" wire:navigate class="size-12 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                @if ($this->call->host->profile?->avatarUrl())
                    <img src="{{ $this->call->host->profile->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-5" />
                    </div>
                @endif
            </a>

            <div class="min-w-0 flex-1">
                <a href="{{ route('profile.show', $this->call->host) }}" wire:navigate class="block truncate font-medium text-stone-900 hover:underline dark:text-white">{{ $this->call->host->name }}</a>
                <p class="text-xs text-stone-500 dark:text-stone-400">{{ __('Incoming call…') }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <flux:button wire:click="respond(false)" size="sm" variant="danger" icon="phone-x-mark" aria-label="{{ __('Decline') }}" />
                <flux:button wire:click="respond(true)" size="sm" variant="primary" color="green" icon="phone" aria-label="{{ __('Accept') }}" />
            </div>
        </div>
    @endif
</div>
