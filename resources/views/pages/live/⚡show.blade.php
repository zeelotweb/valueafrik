<?php

use App\Models\LiveSession;
use App\Services\LiveKitToken;
use Illuminate\Support\Facades\Auth;
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
        $this->wsUrl = (string) config('services.livekit.url');
        $this->configured = filled(config('services.livekit.api_key')) && filled(config('services.livekit.api_secret')) && filled($this->wsUrl);

        if ($this->session->isLive() && $this->configured) {
            $this->token = LiveKitToken::generate($this->session, Auth::user());
        }
    }

    public function endSession(): void
    {
        abort_unless(Auth::id() === $this->session->host_id, 403);

        $this->session->update(['status' => LiveSession::STATUS_ENDED, 'ended_at' => now()]);

        $this->redirect(route('live.index'), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $session->title ?: ucfirst($session->type) }}</flux:heading>
            <flux:subheading>{{ __('hosted by') }} {{ $session->host->name }}</flux:subheading>
        </div>

        @if (Auth::id() === $session->host_id && $session->isLive())
            <flux:button wire:click="endSession" variant="danger" wire:confirm="{{ __('End this session for everyone?') }}">
                {{ __('End session') }}
            </flux:button>
        @else
            <flux:button :href="route('live.index')" wire:navigate variant="ghost">
                {{ __('Back') }}
            </flux:button>
        @endif
    </div>

    @if (! $session->isLive())
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
