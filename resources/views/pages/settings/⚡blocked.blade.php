<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Blocked users')] class extends Component {
    #[Computed]
    public function blockedUsers()
    {
        return Auth::user()->blocking()->with('profile')->orderBy('name')->get();
    }

    public function unblock(int $userId): void
    {
        $user = User::findOrFail($userId);

        Auth::user()->unblock($user);

        unset($this->blockedUsers);

        Flux::toast(text: __(':name has been unblocked.', ['name' => $user->name]));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Blocked users') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Blocked users')" :subheading="__('People you\'ve blocked can no longer follow or message you')">
        <div class="mt-6 space-y-2">
            @forelse ($this->blockedUsers as $blockedUser)
                <div class="flex items-center gap-3 rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800" wire:key="blocked-{{ $blockedUser->id }}">
                    <a href="{{ route('profile.show', $blockedUser) }}" wire:navigate class="size-9 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($blockedUser->profile?->avatarUrl())
                            <img src="{{ $blockedUser->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-4" />
                            </div>
                        @endif
                    </a>

                    <a href="{{ route('profile.show', $blockedUser) }}" wire:navigate class="min-w-0 flex-1 truncate font-medium text-stone-900 hover:underline dark:text-white">
                        {{ $blockedUser->name }}
                    </a>

                    <flux:button size="sm" variant="ghost" wire:click="unblock({{ $blockedUser->id }})" data-test="unblock-button">
                        {{ __('Unblock') }}
                    </flux:button>
                </div>
            @empty
                <p class="text-sm text-stone-500 dark:text-stone-400">{{ __("You haven't blocked anyone.") }}</p>
            @endforelse
        </div>
    </x-pages::settings.layout>
</section>
