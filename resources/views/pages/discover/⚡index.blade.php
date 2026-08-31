<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Discover')] class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'users' => User::query()
                ->whereKeyNot(Auth::id())
                ->with(['profile', 'heritages'])
                ->latest()
                ->paginate(20),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Discover') }}</flux:heading>
    <flux:subheading>{{ __('Everyone on valueAFRIK — browse and connect.') }}</flux:subheading>

    <div class="mt-6 grid gap-3 sm:grid-cols-2">
        @forelse ($users as $person)
            <a
                href="{{ route('profile.show', $person) }}"
                wire:navigate
                class="flex items-center gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                <div class="size-12 shrink-0 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    @if ($person->profile?->avatarUrl())
                        <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-zinc-500">
                            <flux:icon.user class="size-6" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $person->name }}</div>
                    @if ($person->heritages->isNotEmpty())
                        <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $person->heritages->pluck('name')->join(', ') }}
                        </p>
                    @endif
                </div>
            </a>
        @empty
            <div class="col-span-2 rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                <flux:text>{{ __('No one else here yet.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
