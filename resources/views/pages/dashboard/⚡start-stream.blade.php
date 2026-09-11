<?php

use App\Models\LiveSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $class = '';

    public function openVisibilityChoice(): void
    {
        $this->modal('start-stream-visibility')->show();
    }

    public function startStream(string $visibility): void
    {
        abort_unless(in_array($visibility, [LiveSession::VISIBILITY_PUBLIC, LiveSession::VISIBILITY_FOLLOWERS], true), 422);

        $session = LiveSession::startStream(Auth::user(), visibility: $visibility);

        $this->redirect(route('live.show', $session), navigate: true);
    }
}; ?>

<div>
    <flux:button wire:click="openVisibilityChoice" wire:loading.attr="disabled" variant="ghost" icon="video-camera" class="{{ $class }}" data-test="start-stream-button">
        {{ __('Start a stream') }}
    </flux:button>

    <flux:modal name="start-stream-visibility" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Who can watch?') }}</flux:heading>

            <div class="space-y-2">
                <flux:button
                    wire:click="startStream('public')"
                    wire:loading.attr="disabled"
                    variant="ghost"
                    icon="globe-alt"
                    class="w-full justify-start"
                    data-test="start-stream-public"
                >
                    <div class="text-start">
                        <div class="font-medium">{{ __('Public') }}</div>
                        <div class="text-xs text-stone-500 dark:text-stone-400">{{ __('Anyone on valueAFRIK can watch.') }}</div>
                    </div>
                </flux:button>

                <flux:button
                    wire:click="startStream('followers')"
                    wire:loading.attr="disabled"
                    variant="ghost"
                    icon="user-group"
                    class="w-full justify-start"
                    data-test="start-stream-followers"
                >
                    <div class="text-start">
                        <div class="font-medium">{{ __('Followers only') }}</div>
                        <div class="text-xs text-stone-500 dark:text-stone-400">{{ __('Only people who follow you can watch.') }}</div>
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
