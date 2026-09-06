<?php

use App\Models\LiveSession;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live')] class extends Component {
    public LiveSession $session;
    public string $token = '';
    public string $wsUrl = '';
    public bool $configured = true;

    public function mount(LiveSession $liveSession): void
    {
        $this->session = $liveSession;

        if ($this->session->type === LiveSession::TYPE_CALL) {
            if (! $this->session->isParticipant(Auth::user())) {
                Log::warning('Blocked a non-participant from opening a call room.', [
                    'session_id' => $this->session->id,
                    'session_host_id' => $this->session->host_id,
                    'session_callee_id' => $this->session->callee_id,
                    'auth_id' => Auth::id(),
                ]);

                abort(403);
            }

            $this->session->expireIfStale();
        }

        $this->wsUrl = (string) config('services.livekit.url');
        $this->configured = filled(config('services.livekit.api_key')) && filled(config('services.livekit.api_secret')) && filled($this->wsUrl);

        $this->issueTokenIfLive();
    }

    private function issueTokenIfLive(): void
    {
        if ($this->session->isLive() && $this->configured && $this->token === '') {
            $this->token = LiveKitToken::generate($this->session, Auth::user());
        }
    }

    #[Computed]
    public function isHost(): bool
    {
        return Auth::id() === $this->session->host_id;
    }

    #[Computed]
    public function isCallee(): bool
    {
        return Auth::id() === $this->session->callee_id;
    }

    #[Computed]
    public function otherParty()
    {
        return $this->session->otherParty(Auth::user());
    }

    #[Computed]
    public function ringDeadline(): ?string
    {
        return $this->session->isRinging()
            ? $this->session->started_at->addSeconds((int) config('calls.ring_seconds'))->toIso8601String()
            : null;
    }

    public function respond(bool $accept): void
    {
        $this->session->respondToRing(Auth::user(), $accept);
        $this->session->refresh();

        $this->issueTokenIfLive();

        if (! $accept) {
            $this->redirect(route('live.index'), navigate: true);
        }
    }

    public function endSession(): void
    {
        if ($this->session->type === LiveSession::TYPE_STREAM) {
            abort_unless(Auth::id() === $this->session->host_id, 403);

            $this->session->update(['status' => LiveSession::STATUS_ENDED, 'ended_at' => now()]);
        } else {
            $this->session->endOrCancel(Auth::user());
        }

        $this->redirect(route('live.index'), navigate: true);
    }

    public function getListeners(): array
    {
        return Auth::check()
            ? ['echo-private:App.Models.User.'.Auth::id().',.CallStatusUpdated' => 'onCallStatusUpdated']
            : [];
    }

    public function onCallStatusUpdated(array $event): void
    {
        if ((int) ($event['session_id'] ?? 0) !== $this->session->id) {
            return;
        }

        $this->session->refresh();
        $this->issueTokenIfLive();
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $session->title ?: ucfirst($session->type) }}</flux:heading>
            <flux:subheading>
                @if ($session->type === LiveSession::TYPE_CALL)
                    {{ __('with') }} {{ $this->otherParty?->name ?? __('Unknown') }}
                @else
                    {{ __('hosted by') }} {{ $session->host->name }}
                @endif
            </flux:subheading>
        </div>

        @if ($session->type === LiveSession::TYPE_STREAM && Auth::id() === $session->host_id && $session->isLive())
            <flux:button wire:click="endSession" variant="danger" wire:confirm="{{ __('End this session for everyone?') }}">
                {{ __('End session') }}
            </flux:button>
        @elseif ($session->type === LiveSession::TYPE_CALL && $session->isLive())
            <flux:button wire:click="endSession" variant="danger">
                {{ __('End call') }}
            </flux:button>
        @else
            <flux:button :href="route('live.index')" wire:navigate variant="ghost">
                {{ __('Back') }}
            </flux:button>
        @endif
    </div>

    @if ($session->type === LiveSession::TYPE_CALL && $session->isRinging())
        {{-- Ringing: a different screen depending on which side of the call you're on. --}}
        <div
            x-data="{ deadline: @js($this->ringDeadline), secondsLeft: 0, tick() { this.secondsLeft = Math.max(0, Math.round((new Date(this.deadline) - new Date()) / 1000)); } }"
            x-init="tick(); setInterval(tick, 1000)"
            class="mt-10 flex flex-col items-center gap-4 rounded-xl border border-stone-200 bg-white p-10 text-center dark:border-stone-800 dark:bg-stone-900"
        >
            <div class="size-20 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                @if ($this->otherParty?->profile?->avatarUrl())
                    <img src="{{ $this->otherParty->profile->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-8" />
                    </div>
                @endif
            </div>

            @if ($this->isCallee)
                <div>
                    <p class="text-lg font-semibold text-stone-900 dark:text-white">{{ $this->otherParty?->name }} {{ __('is calling you') }}</p>
                    <p class="mt-1 text-sm text-stone-500 dark:text-stone-400" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button wire:click="respond(false)" variant="danger" icon="phone-x-mark">
                        {{ __('Decline') }}
                    </flux:button>
                    <flux:button wire:click="respond(true)" variant="primary" color="green" icon="phone">
                        {{ __('Accept') }}
                    </flux:button>
                </div>
            @else
                <div>
                    <p class="text-lg font-semibold text-stone-900 dark:text-white">{{ __('Calling') }} {{ $this->otherParty?->name }}…</p>
                    <p class="mt-1 text-sm text-stone-500 dark:text-stone-400" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                </div>

                <flux:button wire:click="endSession" variant="danger">
                    {{ __('Cancel') }}
                </flux:button>
            @endif
        </div>
    @elseif ($session->type === LiveSession::TYPE_CALL && in_array($session->status, [LiveSession::STATUS_MISSED, LiveSession::STATUS_DECLINED, LiveSession::STATUS_CANCELED]))
        <div class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>
                @if ($session->status === LiveSession::STATUS_MISSED)
                    @if ($this->isHost && $session->ended_reason === LiveSession::REASON_OFFLINE)
                        {{ $this->otherParty?->name }} {{ __('is offline — they\'ve been notified you tried to call.') }}
                    @elseif ($this->isHost)
                        {{ $this->otherParty?->name }} {{ __("didn't answer.") }}
                    @else
                        {{ __('You missed a call from') }} {{ $this->otherParty?->name }}.
                    @endif
                @elseif ($session->status === LiveSession::STATUS_DECLINED)
                    {{ $this->isHost ? ($this->otherParty?->name.' '.__('declined the call.')) : __('You declined the call.') }}
                @else
                    {{ $this->isHost ? __('You canceled the call.') : ($this->otherParty?->name.' '.__('canceled the call.')) }}
                @endif
            </flux:text>
        </div>
    @elseif (! $session->isLive())
        <div class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('This session has ended.') }}</flux:text>
        </div>
    @elseif (! $configured)
        <div class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __("Live video isn't configured yet — set LIVEKIT_URL, LIVEKIT_API_KEY, and LIVEKIT_API_SECRET to enable it.") }}</flux:text>
        </div>
    @else
        <div
            x-data="{ liveRoom: null, connected: false, error: null, mediaError: null }"
            x-init="
                liveRoom = window.createLiveRoom({
                    wsUrl: @js($wsUrl),
                    token: @js($token),
                    canPublish: @js($session->canPublish(Auth::user())),
                });
                liveRoom.connect($refs.grid)
                    .then((result) => { connected = true; mediaError = result.mediaError; })
                    .catch((e) => error = e.message);
            "
            x-on:beforeunload.window="liveRoom?.disconnect()"
            class="mt-6"
        >
            <template x-if="error">
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" x-text="error"></div>
            </template>

            <template x-if="connected && mediaError">
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                    {{ __("Connected, but your camera/mic couldn't be reached — you can still see and hear everyone else.") }}
                </div>
            </template>

            <p class="text-sm text-stone-500 dark:text-stone-400" x-show="!connected && !error">{{ __('Connecting…') }}</p>

            <div x-ref="grid" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"></div>
        </div>
    @endif
</div>
