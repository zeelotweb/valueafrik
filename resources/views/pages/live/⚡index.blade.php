<?php

use App\Models\LiveSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Live')] class extends Component {
    public function startCall()
    {
        return $this->start(LiveSession::TYPE_CALL);
    }

    public function startStream()
    {
        return $this->start(LiveSession::TYPE_STREAM);
    }

    private function start(string $type)
    {
        $session = LiveSession::create([
            'host_id' => Auth::id(),
            'room_name' => (string) Str::uuid(),
            'type' => $type,
            'status' => LiveSession::STATUS_LIVE,
            'started_at' => now(),
        ]);

        return $this->redirect(route('live.show', $session), navigate: true);
    }

    public function with(): array
    {
        return [
            'sessions' => Auth::user()->liveSessions()->latest()->limit(10)->get(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Live') }}</flux:heading>
    <flux:subheading>{{ __('Basic video calls and streams — the foundation for what gets built on top later.') }}</flux:subheading>

    <div class="mt-6 flex gap-3">
        <flux:button wire:click="startCall" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:loading.attr="disabled">
            {{ __('Start a call') }}
        </flux:button>
        <flux:button wire:click="startStream" variant="ghost" wire:loading.attr="disabled">
            {{ __('Start a stream') }}
        </flux:button>
    </div>

    <div class="mt-8 space-y-2">
        @forelse ($sessions as $item)
            <a
                href="{{ route('live.show', $item) }}"
                wire:navigate
                class="flex items-center justify-between rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                <div>
                    <div class="font-medium text-zinc-900 dark:text-white">
                        {{ $item->title ?: ucfirst($item->type) }}
                    </div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $item->started_at->diffForHumans() }}
                    </div>
                </div>
                <flux:badge size="sm" :color="$item->isLive() ? 'cyan' : 'zinc'">
                    {{ $item->isLive() ? __('Live') : __('Ended') }}
                </flux:badge>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                <flux:text>{{ __("You haven't started a live session yet.") }}</flux:text>
            </div>
        @endforelse
    </div>
</div>
