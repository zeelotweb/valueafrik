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
    @unless (Auth::user()->hasBlockRelationWith($user))
        <flux:button
            wire:click="startCall"
            wire:loading.attr="disabled"
            size="sm"
            variant="ghost"
            icon="video-camera"
            class="{{ $overlay ? '!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900' : '' }} max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!"
            data-test="start-call-button"
        >
            <span class="hidden sm:inline">{{ __('Call') }}</span>
        </flux:button>
    @endunless
</div>
