<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.'.Auth::id().',.notification.created' => '$refresh',
        ];
    }

    public function with(): array
    {
        return ['count' => Auth::user()->unreadNotifications()->count()];
    }
}; ?>

<span>
    @if ($count > 0)
        <flux:badge size="sm" color="cyan" class="ms-auto">{{ $count }}</flux:badge>
    @endif
</span>
