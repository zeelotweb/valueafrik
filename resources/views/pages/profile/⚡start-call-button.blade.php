<?php

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public User $user;

    public function startCall()
    {
        $session = LiveSession::startCallWith(Auth::user(), $this->user);

        return $this->redirect(route('live.show', $session), navigate: true);
    }
}; ?>

<flux:button
    wire:click="startCall"
    wire:loading.attr="disabled"
    size="sm"
    variant="ghost"
    icon="video-camera"
    data-test="start-call-button"
>
    {{ __('Call') }}
</flux:button>
