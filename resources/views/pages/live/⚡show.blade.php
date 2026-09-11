<?php

use App\Models\LiveSession;
use App\Models\User;
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

        if ($this->session->type === LiveSession::TYPE_STREAM) {
            abort_unless($this->session->canView(Auth::user()), 403);
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

    /**
     * Calls are always exactly two people, so a spotlight layout (the other
     * person fills the stage, your own tile floats as a small inset) makes
     * sense. Streams/sprints keep the equal-tile grid.
     */
    #[Computed]
    public function isSpotlightLayout(): bool
    {
        return $this->session->type === LiveSession::TYPE_CALL;
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

    /**
     * Where the "Back" button on a non-live session (ended/missed/declined
     * call, or a stream you're just viewing/it already ended) sends you.
     * A finished call is personal — the other person is the relevant place
     * to go back to, not the live directory. Everything else (streams,
     * sprints, or a call with no resolvable other party) falls back to it.
     */
    #[Computed]
    public function backRoute(): string
    {
        if ($this->session->type === LiveSession::TYPE_CALL && $this->otherParty) {
            return route('profile.show', $this->otherParty);
        }

        return route('live.index');
    }

    #[Computed]
    public function ringDeadline(): ?string
    {
        return $this->session->isRinging()
            ? $this->session->started_at->addSeconds((int) config('calls.ring_seconds'))->toIso8601String()
            : null;
    }

    /**
     * Host-only: viewers currently waiting on a collaborate decision.
     */
    #[Computed]
    public function pendingCollaboratorRequests()
    {
        if ($this->session->type !== LiveSession::TYPE_STREAM || ! $this->isHost) {
            return collect();
        }

        return $this->session->pendingCollaboratorRequests()->with('user')->get();
    }

    #[Computed]
    public function approvedCollaborators()
    {
        if ($this->session->type !== LiveSession::TYPE_STREAM) {
            return collect();
        }

        return $this->session->collaborators()->whereNotNull('approved_at')->with('user')->get();
    }

    /**
     * 'none' | 'pending' | 'approved' — drives which button/state a viewer
     * (never the host) sees for collaborating on this stream.
     */
    #[Computed]
    public function myCollaborationStatus(): string
    {
        if ($this->session->type !== LiveSession::TYPE_STREAM || $this->isHost) {
            return 'none';
        }

        $row = $this->session->collaborators()->where('user_id', Auth::id())->first();

        if (! $row) {
            return 'none';
        }

        return $row->isApproved() ? 'approved' : 'pending';
    }

    #[Computed]
    public function canRequestCollaboration(): bool
    {
        return $this->session->type === LiveSession::TYPE_STREAM
            && ! $this->isHost
            && $this->session->isLive()
            && $this->myCollaborationStatus === 'none'
            && Auth::user()->canCollaborateOnStreams();
    }

    public function requestCollaboration(): void
    {
        $this->session->requestCollaboration(Auth::user());
        unset($this->myCollaborationStatus, $this->canRequestCollaboration);
    }

    public function approveCollaborator(int $userId): void
    {
        $this->session->approveCollaborator(Auth::user(), User::findOrFail($userId));
        unset($this->pendingCollaboratorRequests, $this->approvedCollaborators);
    }

    public function removeCollaborator(int $userId): void
    {
        $this->session->removeCollaborator(Auth::user(), User::findOrFail($userId));
        unset($this->pendingCollaboratorRequests, $this->approvedCollaborators);
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

    /**
     * The control bar's leave/end button, shown to every connected
     * participant — a stream viewer only gets to leave, not end it for the
     * host, so this routes to the right one instead of hitting endSession's
     * host-only guard.
     */
    public function leaveRoom(): void
    {
        if ($this->session->type === LiveSession::TYPE_STREAM && Auth::id() !== $this->session->host_id) {
            $this->redirect(route('live.index'), navigate: true);

            return;
        }

        $this->endSession();
    }

    public function getListeners(): array
    {
        return Auth::check()
            ? [
                'echo-private:App.Models.User.'.Auth::id().',.CallStatusUpdated' => 'onCallStatusUpdated',
                'echo-private:App.Models.User.'.Auth::id().',.StreamCollaborationUpdated' => 'onStreamCollaborationUpdated',
            ]
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

    /**
     * Reacts on both sides: the host's pending-requests list just needs a
     * re-render (the computed props recompute fresh from the DB either
     * way), but the specific collaborator whose access just changed needs
     * an actual new token — canPublish flips server-side, and the browser
     * has to disconnect/reconnect the LiveKit room to pick that up, since
     * publish permissions are baked into the token at connect time.
     */
    public function onStreamCollaborationUpdated(array $event): void
    {
        if ((int) ($event['session_id'] ?? 0) !== $this->session->id) {
            return;
        }

        $this->session->refresh();

        $isMe = (int) ($event['collaborator_id'] ?? 0) === Auth::id();
        $status = $event['status'] ?? null;

        if ($isMe && in_array($status, ['approved', 'removed'], true)) {
            $this->token = '';
            $this->issueTokenIfLive();

            $this->dispatch('live-permissions-changed', token: $this->token, canPublish: $this->session->canPublish(Auth::user()));
        }
    }
}; ?>

<style>
    /* A stream's tiles (not the spotlight layout calls/sprints use) keep a
       fixed 16:9 shape via aspect-video normally — right for a card in the
       page, but it leaves the tile the same size while the stage around it
       balloons to fill the whole screen in fullscreen. Dropping the ratio
       there lets the tile (and the object-cover video inside it) actually
       fill the available height instead of just the width. */
    .stage-fullscreen [data-tile] {
        aspect-ratio: auto;
        height: 100%;
    }
</style>

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
            <flux:button :href="$this->backRoute" wire:navigate variant="ghost">
                {{ __('Back') }}
            </flux:button>
        @endif
    </div>

    @if ($session->type === LiveSession::TYPE_CALL && $session->isRinging())
        {{-- Ringing: a different screen depending on which side of the call you're on. --}}
        <div
            wire:key="live-session-{{ $session->id }}-ringing"
            x-data="{ deadline: @js($this->ringDeadline), secondsLeft: 0, tick() { this.secondsLeft = Math.max(0, Math.round((new Date(this.deadline) - new Date()) / 1000)); } }"
            x-init="tick(); let interval = setInterval(tick, 1000); $cleanup(() => clearInterval(interval))"
            class="mt-10 flex flex-col items-center gap-4 rounded-xl border border-stone-200 bg-white p-10 text-center dark:border-stone-800 dark:bg-stone-900"
        >
            @if ($this->otherParty)
                <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="size-20 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($this->otherParty->profile?->avatarUrl())
                        <img src="{{ $this->otherParty->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-8" />
                        </div>
                    @endif
                </a>
            @else
                <div class="size-20 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    <div class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user class="size-8" />
                    </div>
                </div>
            @endif

            @if ($this->isCallee)
                <div>
                    <p class="text-lg font-semibold text-stone-900 dark:text-white">
                        @if ($this->otherParty)
                            <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="hover:underline">{{ $this->otherParty->name }}</a>
                        @endif
                        {{ __('is calling you') }}
                    </p>
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
                    <p class="text-lg font-semibold text-stone-900 dark:text-white">
                        {{ __('Calling') }}
                        @if ($this->otherParty)
                            <a href="{{ route('profile.show', $this->otherParty) }}" wire:navigate class="hover:underline">{{ $this->otherParty->name }}</a>
                        @endif
                        …
                    </p>
                    <p class="mt-1 text-sm text-stone-500 dark:text-stone-400" x-text="secondsLeft + ' {{ __('s') }}'"></p>
                </div>

                <flux:button wire:click="endSession" variant="danger">
                    {{ __('Cancel') }}
                </flux:button>
            @endif
        </div>
    @elseif ($session->type === LiveSession::TYPE_CALL && in_array($session->status, [LiveSession::STATUS_MISSED, LiveSession::STATUS_DECLINED, LiveSession::STATUS_CANCELED]))
        <div wire:key="live-session-{{ $session->id }}-ended-{{ $session->status }}" class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
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
        <div wire:key="live-session-{{ $session->id }}-ended" class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('This session has ended.') }}</flux:text>
        </div>
    @elseif (! $configured)
        <div wire:key="live-session-{{ $session->id }}-not-configured" class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __("Live video isn't configured yet — set LIVEKIT_URL, LIVEKIT_API_KEY, and LIVEKIT_API_SECRET to enable it.") }}</flux:text>
        </div>
    @else
        <div
            wire:key="live-session-{{ $session->id }}-room"
            x-data="{
                liveRoom: null,
                connected: false,
                error: null,
                mediaError: null,
                canPublish: @js($session->canPublish(Auth::user())),
                micOn: true,
                cameraOn: true,
                deafened: false,
                showReactions: false,
                reactions: [],
                nextReactionId: 0,
                viewerCount: 1,
                fullscreen: false,
                // iOS Safari (and any WebKit-based browser there, since
                // they all share it) has no Fullscreen API for anything but
                // a bare <video> element — requestFullscreen() on the stage
                // div just silently rejects. This is a CSS-only stand-in
                // for that case: a fixed overlay instead of a real
                // fullscreen transition.
                cssFullscreen: false,

                init() {
                    this.liveRoom = window.createLiveRoom({
                        wsUrl: @js($wsUrl),
                        token: @js($token),
                        canPublish: this.canPublish,
                    });

                    this.liveRoom.connect(this.$refs.grid, {
                        onReaction: (emoji) => this.spawnReaction(emoji),
                        onParticipantCountChanged: (count) => this.viewerCount = count,
                        showPlaceholderTiles: @js($session->type !== LiveSession::TYPE_STREAM),
                    })
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

                sendReaction(emoji) {
                    this.liveRoom.sendReaction(emoji);
                    this.spawnReaction(emoji);
                    this.showReactions = false;
                },

                spawnReaction(emoji) {
                    const id = this.nextReactionId++;
                    this.reactions.push({ id, emoji, left: 10 + Math.random() * 75 });
                    setTimeout(() => { this.reactions = this.reactions.filter((r) => r.id !== id); }, 1600);
                },

                // A stream collaborator's publish permission is baked into
                // their LiveKit token at connect time — there's no way to
                // upgrade/downgrade an existing connection in place, so
                // getting approved (or removed) means dropping the room and
                // rejoining fresh with a token that reflects it.
                async reconnectRoom(newToken, newCanPublish) {
                    await this.liveRoom?.disconnect();
                    this.$refs.grid.innerHTML = '';
                    this.connected = false;
                    this.canPublish = newCanPublish;
                    this.micOn = newCanPublish;
                    this.cameraOn = newCanPublish;

                    this.liveRoom = window.createLiveRoom({
                        wsUrl: @js($wsUrl),
                        token: newToken,
                        canPublish: newCanPublish,
                    });

                    this.liveRoom.connect(this.$refs.grid, {
                        onReaction: (emoji) => this.spawnReaction(emoji),
                        onParticipantCountChanged: (count) => this.viewerCount = count,
                        showPlaceholderTiles: @js($session->type !== LiveSession::TYPE_STREAM),
                    })
                        .then((result) => {
                            this.connected = true;
                            this.mediaError = result.mediaError;
                            if (this.mediaError) {
                                this.micOn = false;
                                this.cameraOn = false;
                            }
                        })
                        .catch((e) => this.error = e.message);
                },
            }"
            x-on:beforeunload.window="liveRoom?.disconnect()"
            x-on:live-permissions-changed.window="reconnectRoom($event.detail.token, $event.detail.canPublish)"
            class="mt-6"
        >
            <template x-if="error">
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" x-text="error"></div>
            </template>

            <template x-if="connected && mediaError">
                <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                    {{ __("Connected, but your camera/mic couldn't be reached — you can still see and hear everyone else.") }}
                </div>
            </template>

            @if ($session->type === LiveSession::TYPE_STREAM)
                @if ($this->isHost)
                    @if ($this->pendingCollaboratorRequests->isNotEmpty() || $this->approvedCollaborators->isNotEmpty())
                        <div class="mb-3 space-y-2">
                            @foreach ($this->pendingCollaboratorRequests as $request)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm dark:border-cyan-900 dark:bg-cyan-950">
                                    <span class="text-cyan-800 dark:text-cyan-300">{{ $request->user->name }} {{ __('wants to collaborate') }}</span>
                                    <div class="flex items-center gap-2">
                                        <flux:button size="sm" variant="ghost" wire:click="removeCollaborator({{ $request->user_id }})">{{ __('Deny') }}</flux:button>
                                        <flux:button size="sm" variant="primary" color="cyan" wire:click="approveCollaborator({{ $request->user_id }})">{{ __('Approve') }}</flux:button>
                                    </div>
                                </div>
                            @endforeach

                            @foreach ($this->approvedCollaborators as $collaborator)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm dark:border-stone-800 dark:bg-stone-900">
                                    <span class="text-stone-700 dark:text-stone-300">{{ $collaborator->user->name }} {{ __('is collaborating') }}</span>
                                    <flux:button size="sm" variant="ghost" wire:click="removeCollaborator({{ $collaborator->user_id }})">{{ __('Remove') }}</flux:button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @elseif ($this->myCollaborationStatus === 'pending')
                    <div class="mb-3 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-700 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-300">
                        {{ __('Waiting for the host to approve your request to collaborate…') }}
                    </div>
                @elseif ($this->canRequestCollaboration)
                    <div class="mb-3 flex items-center justify-between gap-3 rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm dark:border-stone-800 dark:bg-stone-900">
                        <span class="text-stone-600 dark:text-stone-400">{{ __('Want to join the broadcast?') }}</span>
                        <flux:button size="sm" variant="primary" color="cyan" wire:click="requestCollaboration">{{ __('Request to collaborate') }}</flux:button>
                    </div>
                @endif
            @endif

            <div
                x-ref="stage"
                class="isolate flex min-h-[60vh] flex-col overflow-hidden bg-zinc-900"
                {{-- relative/fixed can't both be in the class list at once — same
                    specificity, so whichever Tailwind happens to define later in
                    the stylesheet always wins the tie, regardless of source order
                    here. Keeping position entirely inside this one binding is what
                    actually guarantees the switch takes effect.

                    stage-fullscreen also fires for NATIVE fullscreen (not just
                    the CSS fallback) — see the <style> block below for why a
                    stream's video tile needs it: aspect-video keeps a stream
                    tile at a fixed 16:9 shape, which looks right in the normal
                    card but leaves black bars above/below once the stage
                    itself expands to fill an entire (often taller) screen. --}}
                :class="{
                    'fixed inset-0 z-50': cssFullscreen,
                    'relative rounded-2xl': !cssFullscreen,
                    'stage-fullscreen': fullscreen || cssFullscreen,
                }"
            >
                <p class="p-6 text-sm text-zinc-400" x-show="!connected && !error">{{ __('Connecting…') }}</p>

                @if ($session->type === LiveSession::TYPE_STREAM)
                    <div
                        x-show="connected"
                        x-cloak
                        class="absolute top-3 right-3 z-20 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur-sm"
                        data-test="viewer-count"
                    >
                        <flux:icon.eye class="size-4" />
                        <span x-text="viewerCount"></span>
                    </div>
                @endif

                {{-- wire:ignore (not .self — that only protects the element's
                    own attributes, not its children) — this div's children
                    are entirely JS-managed, live.js appends/removes video
                    tiles directly. Without plain wire:ignore, any Livewire
                    action fired while the room is open (e.g. approving a
                    collaborator) re-renders this component and morphdom
                    wipes the JS-injected tiles back to the empty div the
                    server actually rendered. The class/data-layout below are
                    only ever set once at initial render anyway — they never
                    need to change again for the life of a given session. --}}
                <div
                    x-ref="grid"
                    wire:ignore
                    @if ($this->isSpotlightLayout) data-layout="spotlight" @endif
                    class="{{ $this->isSpotlightLayout ? 'relative flex-1' : 'grid flex-1 auto-rows-fr grid-cols-1 gap-3 p-3 sm:grid-cols-2' }}"
                ></div>

                {{-- Floating reactions drift up from the control bar and fade out. --}}
                <div class="pointer-events-none absolute inset-x-0 bottom-24 h-40">
                    <template x-for="reaction in reactions" :key="reaction.id">
                        <span
                            class="absolute bottom-0 text-3xl"
                            :style="{ left: reaction.left + '%', animation: 'float-up 1.6s ease-out forwards' }"
                            x-text="reaction.emoji"
                        ></span>
                    </template>
                </div>

                <template x-if="connected">
                    <div class="absolute inset-x-0 bottom-4 flex flex-col items-center gap-2">
                        <div
                            x-show="showReactions"
                            x-transition
                            x-on:click.outside="showReactions = false"
                            class="flex items-center gap-1 rounded-lg bg-black/70 p-1.5 backdrop-blur-sm"
                        >
                            @foreach (['👍', '❤️', '😂', '👏', '🎉', '🌉'] as $emoji)
                                <button
                                    type="button"
                                    x-on:click="sendReaction('{{ $emoji }}')"
                                    class="flex size-9 items-center justify-center rounded-md text-xl hover:bg-white/10"
                                >{{ $emoji }}</button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2 rounded-lg bg-black/70 p-2 backdrop-blur-sm">
                            <template x-if="canPublish">
                                <button
                                    type="button"
                                    x-on:click="toggleMic"
                                    :class="micOn ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-red-500 text-white hover:bg-red-600'"
                                    class="flex size-10 items-center justify-center rounded-md"
                                    :aria-label="micOn ? '{{ __('Mute') }}' : '{{ __('Unmute') }}'"
                                >
                                    <flux:icon icon="microphone" class="size-5" />
                                </button>
                            </template>

                            <template x-if="canPublish">
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
                            </template>

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
                                x-on:click="showReactions = !showReactions"
                                class="flex size-10 items-center justify-center rounded-md bg-white/10 text-white hover:bg-white/20"
                                aria-label="{{ __('Send a reaction') }}"
                            >
                                <flux:icon icon="face-smile" class="size-5" />
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
                                wire:click="leaveRoom"
                                class="ms-1 flex size-10 items-center justify-center rounded-md bg-red-600 text-white hover:bg-red-500"
                                aria-label="{{ $session->type === LiveSession::TYPE_CALL ? __('End call') : __('Leave') }}"
                            >
                                <flux:icon icon="phone-x-mark" class="size-5" />
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @endif
</div>
