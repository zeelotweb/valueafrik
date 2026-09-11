<?php

use App\Models\CultureSprintPool;
use App\Models\Heritage;
use App\Models\LiveSession;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One page, one URL, the whole lifecycle: lobby (pick a topic, optionally a
 * region) → waiting in that line → matched (both sides must accept) → live
 * turn-timer room → ended. There's no dedicated "room" URL the way calls
 * have one — a match is ephemeral, and requiring you to be on this page to
 * participate is deliberate: unlike a call, nothing here should be able to
 * interrupt you from elsewhere in the app.
 */
new #[Title('Culture Sprint')] class extends Component {
    public ?int $activeSessionId = null;

    public bool $waiting = false;

    public string $topic = '';

    public string $region = '';

    public string $token = '';

    public string $wsUrl = '';

    public bool $configured = true;

    public function mount(): void
    {
        CultureSprintPool::pruneStale();

        if ($existing = $this->findMyActiveSession()) {
            $this->activeSessionId = $existing->id;
        } elseif ($myPoolRow = CultureSprintPool::where('user_id', Auth::id())->first()) {
            $this->waiting = true;
            $this->topic = $myPoolRow->topic;
            $this->region = $myPoolRow->region ?? '';
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
    public function topics(): array
    {
        return config('culture_sprints.topics');
    }

    #[Computed]
    public function regions(): array
    {
        return Heritage::REGIONS;
    }

    #[Computed]
    public function session(): ?LiveSession
    {
        if (! $this->activeSessionId) {
            return null;
        }

        // activeSessionId is a plain client-visible property — nothing stops
        // a forged request from setting it to someone else's session ID.
        // This is the backstop: no matter how it got set, only a real
        // participant of an actual sprint ever gets the session back.
        $session = LiveSession::with(['host.profile', 'callee.profile'])->find($this->activeSessionId);

        if (! $session || $session->type !== LiveSession::TYPE_SPRINT || ! $session->isParticipant(Auth::user())) {
            return null;
        }

        return $session;
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

        $this->validate([
            'topic' => ['required', 'string', 'in:'.implode(',', config('culture_sprints.topics'))],
            'region' => ['nullable', 'string', 'in:'.implode(',', Heritage::REGIONS)],
        ]);

        $region = $this->region !== '' ? $this->region : null;

        CultureSprintPool::pruneStale();

        if ($existing = $this->findMyActiveSession()) {
            $this->activeSessionId = $existing->id;

            return;
        }

        // Atomic: locks and removes the matched pair's pool rows in one
        // transaction, so two people searching at the same instant can't
        // both claim the same waiting partner.
        $partner = CultureSprintPool::claimWaitingPartnerFor(Auth::user(), $this->topic, $region);

        if ($partner) {
            $session = LiveSession::startSprintMatch(Auth::user(), $partner, $this->topic);

            $this->activeSessionId = $session->id;
        } else {
            CultureSprintPool::updateOrCreate(
                ['user_id' => Auth::id()],
                ['topic' => $this->topic, 'region' => $region]
            );
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
        $this->session?->completeSprint(Auth::user());
        unset($this->session);
    }

    public function requestRematch(): void
    {
        if (! $this->session || $this->session->status !== LiveSession::STATUS_ENDED) {
            return;
        }

        $session = LiveSession::requestRematch($this->session, Auth::user());

        $this->activeSessionId = $session->id;
        $this->token = '';
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

        $incomingId = (int) ($event['session_id'] ?? 0);

        if (! $incomingId) {
            return;
        }

        // This listener is a public Livewire method — reachable directly
        // with a forged payload, not just via the real broadcast. Never
        // trust $event beyond "something changed, go look" — always
        // re-verify the session and participation against the database
        // before letting it become the active session.
        if (($event['status'] ?? null) === LiveSession::STATUS_RINGING && $incomingId !== $this->activeSessionId) {
            $session = LiveSession::find($incomingId);

            if ($session && $session->type === LiveSession::TYPE_SPRINT && $session->isParticipant(Auth::user())) {
                $this->waiting = false;
                $this->activeSessionId = $incomingId;
                unset($this->session);
            }

            return;
        }

        if ($this->activeSessionId && $incomingId === $this->activeSessionId) {
            unset($this->session);
            $this->issueTokenIfLive();
        }
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Culture Sprint') }}</flux:heading>
    <flux:subheading>{{ __('Pick a topic, signal you\'re in, and get paired for a fast, focused exchange — twenty seconds each.') }}</flux:subheading>

    <div class="mt-6">
        @if (! $this->session && ! $waiting)
            {{-- Lobby: choose a line before taking a number --}}
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
                <div class="rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
                    <flux:icon.globe-alt class="size-8 text-cyan-600 dark:text-cyan-400" />
                    <p class="mt-3 text-sm text-stone-600 dark:text-stone-400">
                        {{ __("You'll be paired with someone else waiting on the same topic. You each get twenty seconds to share what it means in your culture — then it's over.") }}
                    </p>

                    <form wire:submit="joinPool" class="mt-5 space-y-4">
                        <flux:select wire:model="topic" :label="__('Topic')" placeholder="{{ __('Choose a topic…') }}">
                            @foreach ($this->topics as $option)
                                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="region" :label="__('Region you\'re curious about')" :description="__('Optional — leave blank to match with anyone.')">
                            <flux:select.option value="">{{ __('Anywhere') }}</flux:select.option>
                            @foreach ($this->regions as $option)
                                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button type="submit" wire:loading.attr="disabled" variant="primary" color="cyan">
                            {{ __('Signal intent') }}
                        </flux:button>
                    </form>
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
                    {{ __('Waiting for a match on') }} <span class="font-semibold text-stone-900 dark:text-white">{{ $topic }}</span>
                    @if ($region)
                        {{ __('from') }} <span class="font-semibold text-stone-900 dark:text-white">{{ $region }}</span>
                    @endif
                    <span x-text="dots"></span>
                </p>
                <flux:button wire:click="leavePool" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
            </div>
        @elseif ($this->session?->isRinging())
            {{-- Matched — both sides must accept. Keyed on the session id so
                 a rematch (a brand new session) gets a genuinely fresh
                 Alpine component instead of Livewire morphing this element
                 in place and leaving stale x-data (deadline, secondsLeft)
                 from the previous match behind. --}}
            <div
                wire:key="culture-sprint-{{ $this->session->id }}-matched"
                x-data="{ deadline: @js($this->acceptDeadline), secondsLeft: 0, tick() { this.secondsLeft = Math.max(0, Math.round((new Date(this.deadline) - new Date()) / 1000)); } }"
                x-init="tick(); let i = setInterval(tick, 1000); $cleanup(() => clearInterval(i))"
                class="flex flex-col items-center gap-4 rounded-xl border border-cyan-200 bg-white p-10 text-center dark:border-cyan-900 dark:bg-stone-900"
            >
                <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:bg-cyan-950 dark:text-cyan-400">
                    {{ __('Matched!') }}
                </span>

                @if ($this->otherParty)
                    <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="size-16 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($this->otherParty->profile?->avatarUrl())
                            <img src="{{ $this->otherParty->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-7" />
                            </div>
                        @endif
                    </a>

                    <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="text-lg font-semibold text-stone-900 hover:underline dark:text-white">{{ $this->otherParty->name }}</a>
                @endif

                <div class="rounded-lg bg-stone-100 px-4 py-2 dark:bg-stone-800">
                    <p class="text-xs text-stone-500 dark:text-stone-400">{{ __('Topic') }}</p>
                    <p class="text-lg font-bold text-cyan-700 dark:text-cyan-400">{{ $this->session->culture_word }}</p>
                </div>

                @if ($this->iAccepted)
                    <p class="text-sm text-stone-500 dark:text-stone-400">{{ __('Waiting for them to accept…') }}</p>
                    <p class="text-xs text-stone-400 dark:text-stone-500" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                    <flux:button wire:click="respond(false)" size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
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
                        mediaError: null,
                        micOn: true,
                        cameraOn: true,
                        deafened: false,
                        fullscreen: false,
                        // See the call room's identical property for why this
                        // fallback exists — iOS Safari has no Fullscreen API
                        // for anything but a bare <video> element.
                        cssFullscreen: false,
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
                                .then((result) => {
                                    this.connected = true;
                                    this.mediaError = result.mediaError;
                                    if (this.mediaError) {
                                        this.micOn = false;
                                        this.cameraOn = false;
                                    }
                                })
                                .catch((e) => this.error = e.message);

                            document.addEventListener('fullscreenchange', () => this.fullscreen = !!document.fullscreenElement);

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

                        async toggleMic() {
                            this.micOn = !this.micOn;
                            await this.liveRoom.setMicrophoneEnabled(this.micOn);
                        },

                        async toggleCamera() {
                            this.cameraOn = !this.cameraOn;
                            await this.liveRoom.setCameraEnabled(this.cameraOn);
                        },

                        toggleDeafen() {
                            this.deafened = !this.deafened;
                            this.liveRoom.setDeafened(this.deafened);
                        },

                        toggleFullscreen() {
                            const supportsFullscreenApi = document.fullscreenEnabled
                                && typeof this.$refs.stage.requestFullscreen === 'function';

                            if (!supportsFullscreenApi) {
                                this.cssFullscreen = !this.cssFullscreen;
                                return;
                            }

                            if (document.fullscreenElement) {
                                document.exitFullscreen().catch(() => {});
                            } else {
                                this.$refs.stage.requestFullscreen().catch(() => {
                                    this.cssFullscreen = true;
                                });
                            }
                        },
                    }"
                    x-on:beforeunload.window="liveRoom?.disconnect()"
                    {{-- Keyed on the session id — same reasoning as the
                         "matched" screen above. Without this, a rematch
                         reuses this exact DOM node instead of remounting,
                         which means init() never re-runs: the old (now
                         stale) liveRoom connection and turn-timer state
                         from the PREVIOUS sprint just sit there instead of
                         connecting fresh to the new one. --}}
                    wire:key="culture-sprint-{{ $this->session->id }}-live"
                >
                    <template x-if="error">
                        <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" x-text="error"></div>
                    </template>

                    <template x-if="connected && mediaError">
                        <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                            {{ __("Connected, but your camera/mic couldn't be reached — you can still see and hear your match.") }}
                        </div>
                    </template>

                    <div
                        x-ref="stage"
                        class="isolate flex min-h-[50vh] flex-col overflow-hidden bg-zinc-900"
                        :class="cssFullscreen ? 'fixed inset-0 z-50' : 'relative rounded-2xl'"
                    >
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

                        {{-- Same "you as a small draggable inset, your match fills
                             the stage" layout the call room uses — a sprint is
                             always exactly two people too. --}}
                        {{-- wire:ignore (not .self) — see the call room's
                            identical comment; this div's children are
                            entirely JS-managed and must survive any Livewire
                            re-render. --}}
                        <div x-ref="grid" wire:ignore data-layout="spotlight" class="relative flex-1"></div>

                        <div class="absolute inset-x-0 bottom-4 flex justify-center">
                            <div class="flex items-center gap-2 rounded-lg bg-black/70 p-2 backdrop-blur-sm">
                                <button
                                    type="button"
                                    x-on:click="toggleMic"
                                    :class="micOn ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-red-500 text-white hover:bg-red-600'"
                                    class="flex size-10 items-center justify-center rounded-md"
                                    :aria-label="micOn ? '{{ __('Mute') }}' : '{{ __('Unmute') }}'"
                                >
                                    <flux:icon icon="microphone" class="size-5" />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="toggleCamera"
                                    :class="cameraOn ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-red-500 text-white hover:bg-red-600'"
                                    class="flex size-10 items-center justify-center rounded-md"
                                    :aria-label="cameraOn ? '{{ __('Hide video') }}' : '{{ __('Show video') }}'"
                                >
                                    <flux:icon x-show="cameraOn" icon="video-camera" class="size-5" />
                                    <flux:icon x-show="!cameraOn" icon="video-camera-slash" class="size-5" x-cloak />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="toggleDeafen"
                                    :class="deafened ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-white/10 text-white hover:bg-white/20'"
                                    class="flex size-10 items-center justify-center rounded-md"
                                    :aria-label="deafened ? '{{ __('Unmute speaker') }}' : '{{ __('Mute speaker') }}'"
                                >
                                    <flux:icon x-show="!deafened" icon="speaker-wave" class="size-5" />
                                    <flux:icon x-show="deafened" icon="speaker-x-mark" class="size-5" x-cloak />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="toggleFullscreen"
                                    class="flex size-10 items-center justify-center rounded-md bg-white/10 text-white hover:bg-white/20"
                                    :aria-label="(fullscreen || cssFullscreen) ? '{{ __('Exit full screen') }}' : '{{ __('Full screen') }}'"
                                >
                                    <flux:icon x-show="!(fullscreen || cssFullscreen)" icon="arrows-pointing-out" class="size-5" />
                                    <flux:icon x-show="fullscreen || cssFullscreen" icon="arrows-pointing-in" class="size-5" x-cloak />
                                </button>

                                <button
                                    type="button"
                                    wire:click="endEarly"
                                    class="flex size-10 items-center justify-center rounded-md bg-red-500 text-white hover:bg-red-600"
                                    aria-label="{{ __('Leave early') }}"
                                >
                                    <flux:icon icon="phone-x-mark" class="size-5" />
                                </button>
                            </div>
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

                @if ($this->otherParty)
                    <div class="mt-2 flex items-center gap-3 rounded-lg bg-stone-100 px-4 py-3 dark:bg-stone-800">
                        <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="size-10 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                            @if ($this->otherParty->profile?->avatarUrl())
                                <img src="{{ $this->otherParty->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-500">
                                    <flux:icon.user class="size-5" />
                                </div>
                            @endif
                        </a>
                        <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="font-medium text-stone-900 hover:underline dark:text-white">{{ $this->otherParty->name }}</a>
                        <livewire:pages::profile.follow-button :user="$this->otherParty" :icon-only="true" :key="'sprint-follow-'.$this->otherParty->id" />
                    </div>
                @endif

                <div class="mt-2 flex items-center gap-3">
                    @if ($this->session?->status === LiveSession::STATUS_ENDED && $this->otherParty)
                        <flux:button wire:click="requestRematch" variant="primary" color="cyan">
                            {{ __('Go again') }}
                        </flux:button>
                        <flux:button wire:click="backToLobby" variant="ghost">
                            {{ __('Pick another topic') }}
                        </flux:button>
                    @else
                        <flux:button wire:click="backToLobby" variant="primary" color="cyan">
                            {{ __('Pick another topic') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
