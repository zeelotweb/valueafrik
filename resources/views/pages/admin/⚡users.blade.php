<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin — Users')] class extends Component {
    use WithPagination;

    public string $search = '';

    public ?int $banningUserId = null;
    public string $banReason = '';

    /**
     * The route middleware already gates the page's initial load — this is
     * the same check applied directly to the component too, since a
     * Livewire component is also independently reachable through its own
     * update endpoint, not only through the route that first rendered it.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startBan(int $userId): void
    {
        abort_if($userId === Auth::id(), 403);

        $this->banningUserId = $userId;
        $this->banReason = '';

        $this->modal('ban-user')->show();
    }

    public function cancelBan(): void
    {
        $this->reset(['banningUserId', 'banReason']);

        $this->modal('ban-user')->close();
    }

    public function confirmBan(): void
    {
        $this->validate(['banReason' => ['required', 'string', 'max:500']]);

        $user = User::findOrFail($this->banningUserId);

        abort_if($user->id === Auth::id(), 403);

        $user->ban($this->banReason);

        Flux::toast(variant: 'success', text: __(':name has been banned.', ['name' => $user->name]));

        $this->reset(['banningUserId', 'banReason']);

        $this->modal('ban-user')->close();
    }

    public function unban(int $userId): void
    {
        $user = User::findOrFail($userId);

        $user->unban();

        Flux::toast(text: __(':name has been unbanned.', ['name' => $user->name]));
    }

    public function with(): array
    {
        $users = User::query()
            ->with('profile')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('username', 'like', "%{$this->search}%");
                });
            })
            ->latest()
            ->paginate(15);

        return ['users' => $users];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Users') }}</flux:heading>
    <flux:subheading>{{ __('Search anyone on the platform and manage their standing.') }}</flux:subheading>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search by name, email, or username…') }}" class="mt-4" />

    <div class="mt-6 space-y-2">
        @foreach ($users as $user)
            <div class="flex items-center gap-3 rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800" wire:key="admin-user-{{ $user->id }}">
                <a href="{{ route('profile.show', $user) }}" wire:navigate class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($user->profile?->avatarUrl())
                        <img src="{{ $user->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-4" />
                        </div>
                    @endif
                </a>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('profile.show', $user) }}" wire:navigate class="truncate font-medium text-stone-900 hover:underline dark:text-white">{{ $user->name }}</a>
                        @if ($user->isAdmin())
                            <flux:badge size="sm" color="cyan">{{ __('Admin') }}</flux:badge>
                        @endif
                        @if ($user->isBanned())
                            <flux:badge size="sm" color="red">{{ __('Banned') }}</flux:badge>
                        @endif
                    </div>
                    <p class="truncate text-xs text-stone-400 dark:text-stone-500">{{ $user->email }} &middot; {{ '@'.$user->username }}</p>
                    @if ($user->isBanned() && $user->ban_reason)
                        <p class="mt-0.5 truncate text-xs text-red-600 dark:text-red-400">{{ __('Ban reason:') }} {{ $user->ban_reason }}</p>
                    @endif
                </div>

                @unless ($user->id === Auth::id())
                    @if ($user->isBanned())
                        <flux:button size="sm" variant="ghost" wire:click="unban({{ $user->id }})" data-test="unban-button">
                            {{ __('Unban') }}
                        </flux:button>
                    @else
                        <flux:button size="sm" variant="danger" wire:click="startBan({{ $user->id }})" data-test="ban-button">
                            {{ __('Ban') }}
                        </flux:button>
                    @endif
                @endunless
            </div>
        @endforeach
    </div>

    {{ $users->links() }}

    <flux:modal name="ban-user" class="max-w-md" wire:close="cancelBan">
        <form wire:submit="confirmBan" class="space-y-4">
            <flux:heading size="lg">{{ __('Ban this user?') }}</flux:heading>
            <flux:text>{{ __('They will be signed out immediately and unable to log back in until unbanned.') }}</flux:text>

            <flux:textarea wire:model="banReason" :label="__('Reason (kept on record, not shown to the user)')" rows="3" />
            @error('banReason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="cancelBan">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="confirmBan" data-test="confirm-ban-button">
                    {{ __('Ban user') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
