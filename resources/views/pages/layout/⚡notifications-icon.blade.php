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

    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return $this->redirect($notification->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function with(): array
    {
        return [
            'count' => Auth::user()->unreadNotifications()->count(),
            'recent' => Auth::user()->notifications()->latest()->limit(6)->get(),
        ];
    }
}; ?>

<flux:dropdown position="bottom" align="end">
    <button
        type="button"
        class="relative flex items-center justify-center rounded-lg p-2 text-stone-600 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800"
        aria-label="{{ __('Notifications') }}"
    >
        <flux:icon.bell class="size-5" />
        @if ($count > 0)
            <span class="absolute right-1 top-1 flex size-2 rounded-full bg-rose-500"></span>
        @endif
    </button>

    {{-- !p-0 — Flux's own p-[.3125rem] default on flux:menu and this class
         are equal-specificity utilities, so which one wins depends on
         compiled stylesheet order, not markup order; the ! modifier is the
         reliable way to override a component library's built-in utility
         rather than hoping plain p-0 happens to come out ahead. --}}
    <flux:menu class="!p-0 w-80 max-w-[calc(100vw-2rem)]">
        <div class="flex items-center justify-between border-b border-stone-200 px-3 py-2 dark:border-stone-700">
            <span class="text-sm font-semibold text-stone-900 dark:text-white">{{ __('Notifications') }}</span>
            @if ($count > 0)
                <button type="button" wire:click="markAllRead" class="text-xs text-cyan-600 hover:underline dark:text-cyan-400">
                    {{ __('Mark all as read') }}
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($recent as $notification)
                <button
                    type="button"
                    wire:click="open('{{ $notification->id }}')"
                    wire:key="quick-notification-{{ $notification->id }}"
                    class="flex w-full items-start gap-2.5 px-3 py-2.5 text-start hover:bg-stone-50 dark:hover:bg-stone-800"
                >
                    @if (! $notification->read_at)
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-cyan-600"></span>
                    @else
                        <span class="mt-1.5 size-2 shrink-0"></span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm {{ $notification->read_at ? 'text-stone-600 dark:text-stone-400' : 'font-medium text-stone-900 dark:text-white' }}">
                            {{ $notification->data['message'] ?? '' }}
                        </p>
                        <p class="mt-0.5 text-xs text-stone-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-sm text-stone-400">{{ __("Nothing yet — we'll let you know when something happens.") }}</p>
            @endforelse
        </div>

        <a
            href="{{ route('notifications.index') }}"
            wire:navigate
            class="block border-t border-stone-200 px-3 py-2 text-center text-sm font-medium text-cyan-600 hover:bg-stone-50 dark:border-stone-700 dark:text-cyan-400 dark:hover:bg-stone-800"
        >
            {{ __('View more') }}
        </a>
    </flux:menu>
</flux:dropdown>
