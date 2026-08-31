<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

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
            'notifications' => Auth::user()->notifications()->paginate(20),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>

        @if (Auth::user()->unreadNotifications()->exists())
            <flux:button size="sm" variant="ghost" wire:click="markAllRead">{{ __('Mark all as read') }}</flux:button>
        @endif
    </div>

    <div class="mt-6 space-y-2">
        @forelse ($notifications as $notification)
            <button
                type="button"
                wire:click="open('{{ $notification->id }}')"
                wire:key="notification-{{ $notification->id }}"
                class="flex w-full items-start gap-3 rounded-xl border border-zinc-200 p-4 text-start hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                @if (! $notification->read_at)
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-cyan-600"></span>
                @else
                    <span class="mt-1.5 size-2 shrink-0"></span>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="text-sm {{ $notification->read_at ? 'text-zinc-600 dark:text-zinc-400' : 'font-medium text-zinc-900 dark:text-white' }}">
                        {{ $notification->data['message'] ?? '' }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            </button>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                <flux:text>{{ __("Nothing yet — we'll let you know when something happens.") }}</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</div>
