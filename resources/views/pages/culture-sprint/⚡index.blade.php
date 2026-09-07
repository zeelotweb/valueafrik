<?php

use App\Models\CultureSprintPool;
use App\Models\LiveSession;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One page, one URL, the whole lifecycle: lobby → waiting in the pool →
 * matched (both sides must accept) → live turn-timer room → ended. There's
 * no dedicated "room" URL the way calls have one — a match is ephemeral,
 * and requiring you to be on this page to participate is deliberate: unlike
 * a call, nothing here should be able to interrupt you from elsewhere in
 * the app.
 */
new #[Title('Culture Sprint')] class extends Component {
    public ?int $activeSessionId = null;

    public bool $waiting = false;

    public string $token = '';

    public string $wsUrl = '';

    public bool $configured = true;

    public function mount(): void
    {
        CultureSprintPool::pruneStale();

        if ($existing = $this->findMyActiveSession()) {
            $this->activeSessionId = $existing->id;
        } elseif (CultureSprintPool::where('user_id', Auth::id())->exists()) {
            $this->waiting = true;
        }

        $this->wsUrl = (string) config('services.livekit.url');
        $this->configured = filled(config('services.livekit.api_key')) && filled(config('services.livekit.api_secret')) && filled($this->wsUrl);

        $this->issueTokenIfLive();
    }

    private function findMyActiveSession(): ?LiveSession
    {
        return LiveSession::query()
            ->where('type', LiveSession::TYPE_SPRINT)
            ->whereIn('status', [LiveSession::STATUS_RINGING, LiveSession::STATUS_LIVE])
            ->where(fn ($q) => $q->where('host_id', Auth::id())->orWhere('callee_id', Auth::id()))
            ->latest('started_at')
            ->first();
    }

    private function issueTokenIfLive(): void
    {
        if ($this->session?->isLive() && $this->configured && $this->token === '') {
            $this->token = LiveKitToken::generate($this->session, Auth::user());
        }
    }

    #[Computed]
    public function session(): ?LiveSession
    {
        if (! $this->activeSessionId) {
            return null;
        }

        return LiveSession::with(['host.profile', 'callee.profile'])->find($this->activeSessionId);
    }

    #[Computed]
    public function rootsIncomplete(): bool
    {
        $user = Auth::user();

        return ! $user->profile?->bio || $user->languages->isEmpty() || $user->heritages->isEmpty();
    }

    #[Computed]
    public function isHost(): bool
    {
        return $this->session && Auth::id() === $this->session->host_id;
    }

    #[Computed]
    public function otherParty()
    {
        return $this->session?->otherParty(Auth::user());
    }

    #[Computed]
    public function iAccepted(): bool
    {
        return (bool) ($this->isHost ? $this->session?->host_accepted_at : $this->session?->callee_accepted_at);
    }

    #[Computed]
    public function partnerAccepted(): bool
    {
        return (bool) ($this->isHost ? $this->session?->callee_accepted_at : $this->session?->host_accepted_at);
    }

    #[Computed]
    public function acceptDeadline(): ?string
    {
        return $this->session?->isRinging()
            ? $this->session->started_at->addSeconds((int) config('culture_sprints.accept_seconds'))->toIso8601String()
            : null;
    }

    #[Computed]
    public function liveDeadline(): ?string
    {
        return $this->session?->isLive() && $this->session->answered_at
            ? $this->session->answered_at->addSeconds((int) config('culture_sprints.turn_seconds') * 2)->toIso8601String()
            : null;
    }

    public function joinPool(): void
    {
        abort_if($this->rootsIncomplete, 403);

        CultureSprintPool::pruneStale();

        if ($existing = $this->findMyActiveSession()) {
            $this->activeSessionId = $existing->id;

            return;
        }

        $partner = CultureSprintPool::findWaitingPartnerFor(Auth::user());

        if ($partner) {
            CultureSprintPool::where('user_id', $partner->id)->delete();
            CultureSprintPool::where('user_id', Auth::id())->delete();

            $session = LiveSession::startSprintMatch(Auth::user(), $partner, collect(config('culture_sprints.words'))->random());

            $this->activeSessionId = $session->id;
        } else {
            CultureSprintPool::firstOrCreate(['user_id' => Auth::id()]);
            $this->waiting = true;
        }
    }

    public function leavePool(): void
    {
        CultureSprintPool::where('user_id', Auth::id())->delete();
        $this->waiting = false;
    }

    public function respond(bool $accept): void
    {
        $this->session?->respondToSprint(Auth::user(), $accept);
        unset($this->session);
        $this->issueTokenIfLive();
    }

    public function endEarly(): void
    {
        $this->session?->endOrCancel(Auth::user());
        unset($this->session);
    }

    public function complete(): void
    {
        $this->session?->completeSprint();
        unset($this->session);
    }

    public function backToLobby(): void
    {
        $this->activeSessionId = null;
        $this->token = '';
        $this->waiting = false;
    }

    public function getListeners(): array
    {
        return Auth::check()
            ? ['echo-private:App.Models.User.'.Auth::id().',.CallStatusUpdated' => 'onCallStatusUpdated']
            : [];
    }

    public function onCallStatusUpdated(array $event): void
    {
        if (($event['type'] ?? null) !== LiveSession::TYPE_SPRINT) {
            return;
        }

        if ($this->waiting && ($event['status'] ?? null) === LiveSession::STATUS_RINGING) {
            $this->waiting = false;
            $this->activeSessionId = (int) $event['session_id'];
            unset($this->session);

            return;
        }

        if ($this->activeSessionId && (int) ($event['session_id'] ?? 0) === $this->activeSessionId) {
            unset($this->session);
            $this->issueTokenIfLive();
        }
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Culture Sprint') }}</flux:heading>
    <flux:subheading>{{ __('Meet someone new, at random, anywhere in the world — one word, twenty seconds each.') }}</flux:subheading>

    <div class="mt-6">
        @if (! $this->session && ! $waiting)
            {{-- Lobby --}}
            @if ($this->rootsIncomplete)
                <div class="rounded-xl border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
                    <flux:text>{{ __("Finish your Roots first — it's what gives your match something to actually meet.") }}</flux:text>
                    <div class="mt-3">
                        <a href="{{ route('roots.edit') }}" wire:navigate>
                            <flux:button size="sm" variant="primary" color="cyan">{{ __('Finish your Roots') }}</flux:button>
                        </a>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-stone-200 bg-white p-8 text-center dark:border-stone-800 dark:bg-stone-900">
                    <flux:icon.globe-alt class="mx-auto size-10 text-cyan-600 dark:text-cyan-400" />
                    <p class="mt-3 text-sm text-stone-600 dark:text-stone-400">
                        {{ __("We'll pair you with a random stranger and give you both a word. You each get twenty seconds to share what it means in your culture — then it's over.") }}
                    </p>
                    <flux:button wire:click="joinPool" wire:loading.attr="disabled" variant="primary" color="cyan" class="mt-5">
                        {{ __('Find a match') }}
                    </flux:button>
                </div>
            @endif
        @elseif ($waiting)
            {{-- Waiting in the pool. Polling isn't just for the UI — it's what
                 keeps this person's presence heartbeat fresh while they sit
                 here idle, so they don't quietly drop out of "online" and
                 get skipped as a match for someone else. --}}
            <div
                wire:poll.10s
                x-data="{ dots: '' }"
                x-init="setInterval(() => dots = dots.length >= 3 ? '' : dots + '.', 400)"
                class="flex flex-col items-center gap-4 rounded-xl border border-stone-200 bg-white p-10 text-center dark:border-stone-800 dark:bg-stone-900"
            >
                <flux:icon.loading class="size-6 text-cyan-600 dark:text-cyan-400" />
                <p class="text-sm text-stone-600 dark:text-stone-400">
                    {{ __('Looking for someone to match you with') }}<span x-text="dots"></span>
                </p>
                <flux:button wire:click="leavePool" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
            </div>
        @elseif ($this->session?->isRinging())
            {{-- Matched — both sides must accept --}}
            <div
                x-data="{ deadline: @js($this->acceptDeadline), secondsLeft: 0, tick() { this.secondsLeft = Math.max(0, Math.round((new Date(this.deadline) - new Date()) / 1000)); } }"
                x-init="tick(); let i = setInterval(tick, 1000); $cleanup(() => clearInterval(i))"
                class="flex flex-col items-center gap-4 rounded-xl border border-cyan-200 bg-white p-10 text-center dark:border-cyan-900 dark:bg-stone-900"
            >
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                    {{ __('Matched!') }}
                </span>

                <div class="size-16 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($this->otherParty?->profile?->avatarUrl())
                        <img src="{{ $this->otherParty->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-7" />
                        </div>
                    @endif
                </div>

                <p class="text-lg font-semibold text-stone-900 dark:text-white">{{ $this->otherParty?->name }}</p>

                <div class="rounded-lg bg-stone-100 px-4 py-2 dark:bg-stone-800">
                    <p class="text-xs text-stone-500 dark:text-stone-400">{{ __('Your word') }}</p>
                    <p class="text-lg font-bold text-cyan-700 dark:text-cyan-400">{{ $this->session->culture_word }}</p>
                </div>

                @if ($this->iAccepted)
                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('Waiting for them to accept…') }}</p>
                    <p class="text-xs text-stone-400 dark:text-stone-500" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                @else
                    <p class="text-xs text-stone-400 dark:text-stone-500" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                    <div class="flex items-center gap-3">
                        <flux:button wire:click="respond(false)" variant="danger">{{ __('Skip') }}</flux:button>
                        <flux:button wire:click="respond(true)" variant="primary" color="cyan">{{ __("I'm ready") }}</flux:button>
                    </div>
                @endif
            </div>
        @elseif ($this->session?->isLive())
            {{-- Live: turn timer over the same room primitives the call screen uses --}}
            @if (! $this->configured)
                <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                    <flux:text>{{ __("Live video isn't configured yet — set LIVEKIT_URL, LIVEKIT_API_KEY, and LIVEKIT_API_SECRET to enable it.") }}</flux:text>
                </div>
            @else
                <div
                    x-data="{
                        liveRoom: null,
                        connected: false,
                        error: null,
                        deadline: @js($this->liveDeadline),
                        turnSeconds: @js((int) config('culture_sprints.turn_seconds')),
                        isHost: @js($this->isHost),
                        secondsLeft: 0,
                        myTurn: false,

                        init() {
                            this.liveRoom = window.createLiveRoom({
                                wsUrl: @js($wsUrl),
                                token: @js($token),
                                canPublish: true,
                            });

                            this.liveRoom.connect(this.$refs.grid)
                                .then(() => this.connected = true)
                                .catch((e) => this.error = e.message);

                            this.tick();
                            let i = setInterval(() => this.tick(), 250);
                            $cleanup(() => clearInterval(i));
                        },

                        tick() {
                            const remaining = Math.max(0, (new Date(this.deadline) - new Date()) / 1000);
                            this.secondsLeft = Math.ceil(remaining);
                            const firstTurn = remaining > this.turnSeconds;
                            this.myTurn = firstTurn ? this.isHost : ! this.isHost;

                            if (remaining <= 0) {
                                this.$wire.complete();
                            }
                        },
                    }"
                    x-on:beforeunload.window="liveRoom?.disconnect()"
                >
                    <template x-if="error">
                        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" x-text="error"></div>
                    </template>

                    <div class="relative isolate flex min-h-[50vh] flex-col overflow-hidden rounded-2xl bg-zinc-900">
                        <div class="flex items-center justify-between p-4">
                            <div class="rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold text-white">
                                {{ $this->session->culture_word }}
                            </div>
                            <div class="flex items-center gap-2 rounded-lg bg-white/10 px-3 py-1.5 text-sm text-white">
                                <span x-text="myTurn ? '{{ __('Your turn') }}' : '{{ __("Their turn") }}'"></span>
                                <span x-text="secondsLeft"></span>s
                            </div>
                        </div>

                        <p class="px-4 text-sm text-zinc-400" x-show="!connected && !error">{{ __('Connecting…') }}</p>

                        <div x-ref="grid" class="grid flex-1 auto-rows-fr grid-cols-1 gap-3 p-3 sm:grid-cols-2"></div>

                        <div class="flex justify-center p-4">
                            <button
                                type="button"
                                wire:click="endEarly"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500"
                            >
                                {{ __('Leave early') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @else
            {{-- Ended (completed, declined, canceled, or missed) --}}
            <div class="flex flex-col items-center gap-3 rounded-xl border border-stone-200 bg-white p-10 text-center dark:border-stone-800 dark:bg-stone-900">
                @if ($this->session?->status === LiveSession::STATUS_ENDED)
                    <flux:icon.check-circle class="size-8 text-cyan-600 dark:text-cyan-400" />
                    <p class="font-semibold text-stone-900 dark:text-white">{{ __('Sprint complete!') }}</p>
                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('You just learned something new — nice work.') }}</p>
                @elseif ($this->session?->status === LiveSession::STATUS_DECLINED)
                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('Your match skipped this one.') }}</p>
                @else
                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('That match timed out.') }}</p>
                @endif

                <div class="mt-2 flex items-center gap-3">
                    <flux:button wire:click="backToLobby" variant="ghost">{{ __('Done') }}</flux:button>
                    <flux:button wire:click="joinPool" variant="primary" color="cyan">{{ __('Find another match') }}</flux:button>
                </div>
            </div>
        @endif
    </div>
</div>
