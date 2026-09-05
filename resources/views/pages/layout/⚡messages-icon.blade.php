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
        return ['count' => Auth::user()->unreadConversationsCount()];
    }
}; ?>

<a
    href="{{ route('messages.index') }}"
    wire:navigate
    class="relative flex items-center justify-center rounded-lg p-2 text-stone-600 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800"
    aria-label="{{ __('Messages') }}"
>
    <flux:icon.chat-bubble-left-right class="size-5" />
    @if ($count > 0)
        <span class="absolute right-1 top-1 flex size-2 rounded-full bg-rose-500"></span>
    @endif
</a>
