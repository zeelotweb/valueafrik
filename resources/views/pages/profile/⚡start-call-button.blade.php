<?php

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public User $user;
    public bool $overlay = false;

    public function startCall()
    {
        abort_if(Auth::user()->hasBlockRelationWith($this->user), 403);

        $session = LiveSession::startCallWith(Auth::user(), $this->user);

        return $this->redirect(route('live.show', $session), navigate: true);
    }
}; ?>

<div>
    @if (! config('features.live') && ! Auth::user()->hasBlockRelationWith($user))
        <flux:button
            disabled
            size="sm"
            variant="ghost"
            icon="video-camera"
            class="{{ $overlay ? '!bg-live-50/90 !text-live-800 shadow-sm backdrop-blur dark:!bg-live-950/80 dark:!text-live-300' : '' }} max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!"
            data-test="call-coming-soon"
        >
            <span class="hidden items-center gap-2 sm:inline-flex">{{ __('Call') }} <x-coming-soon-badge /></span>
        </flux:button>
    @elseif (config('features.live') && ! Auth::user()->hasBlockRelationWith($user))
        <flux:button
            wire:click="startCall"
            wire:loading.attr="disabled"
            size="sm"
            variant="ghost"
            icon="video-camera"
            class="{{ $overlay ? '!bg-live-50/90 !text-live-800 shadow-sm backdrop-blur hover:!bg-live-50 dark:!bg-live-950/80 dark:!text-live-300 dark:hover:!bg-live-950' : '' }} max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!"
            data-test="start-call-button"
        >
            <span class="hidden sm:inline">{{ __('Call') }}</span>
        </flux:button>
    @endif
</div>
