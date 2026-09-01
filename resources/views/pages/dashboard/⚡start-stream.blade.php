<?php

use App\Models\LiveSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function startStream()
    {
        $session = LiveSession::startStream(Auth::user());

        return $this->redirect(route('live.show', $session), navigate: true);
    }
}; ?>

<flux:button wire:click="startStream" wire:loading.attr="disabled" variant="ghost" icon="video-camera" data-test="start-stream-button">
    {{ __('Start a stream') }}
</flux:button>
