<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component {
    public int $perPage = 20;

    public int $loaded = 20;

    public function loadMore(): void
    {
        if ($this->hasMore) {
            $this->loaded += $this->perPage;

            // #[Computed] only memoizes for the lifetime of a component
            // instance, not a dependency graph — it has no idea
            // notificationsWindow/hasMore depend on $loaded, so without
            // this they'd keep serving the pre-increment values for the
            // rest of this request.
            unset($this->notificationsWindow, $this->hasMore);
        }
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

    /**
     * Fetches one row past the current window so hasMore can tell whether
     * there's more without a separate count() query — the window itself is
     * just the first $loaded of these.
     */
    #[Computed]
    public function notificationsWindow()
    {
        return Auth::user()->notifications()->latest()->limit($this->loaded + 1)->get();
    }

    #[Computed]
    public function hasMore(): bool
    {
        return $this->notificationsWindow->count() > $this->loaded;
    }

    public function with(): array
    {
        return [
            'notifications' => $this->notificationsWindow->take($this->loaded),
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
                class="flex w-full items-start gap-3 rounded-xl bg-white border border-stone-200 p-4 text-start hover:bg-stone-50 dark:bg-stone-900 dark:border-stone-800 dark:hover:bg-stone-800"
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
                    <p class="mt-1 text-xs text-stone-400">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            </button>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __("Nothing yet — we'll let you know when something happens.") }}</flux:text>
            </div>
        @endforelse
    </div>

    @if ($this->hasMore)
        {{-- wire:intersect fires loadMore() the moment this sentinel scrolls
             into view — no separate JS/Alpine plugin needed, Livewire ships
             its own IntersectionObserver-backed directive for exactly this. --}}
        <div wire:intersect="loadMore" wire:key="notifications-load-more" class="flex justify-center py-6">
            <flux:icon.loading class="size-5 text-stone-400" />
        </div>
    @endif
</div>
