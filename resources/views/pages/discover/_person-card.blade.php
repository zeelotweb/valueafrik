<div class="flex items-center gap-3 rounded-xl border border-stone-200 p-4 dark:border-stone-800">
    <a href="{{ route('profile.show', $user) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
        <div class="size-12 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
            @if ($user->profile?->avatarUrl())
                <img src="{{ $user->profile->avatarUrl() }}" class="size-full object-cover">
            @else
                <div class="flex size-full items-center justify-center text-stone-500">
                    <flux:icon.user class="size-6" />
                </div>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="truncate font-medium text-stone-900 dark:text-white">{{ $user->name }}</div>
            <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">{{ $caption }}</p>
        </div>
    </a>

    <livewire:pages::profile.follow-button :user="$user" :key="'discover-follow-'.$user->id" :icon-only="true" />
</div>
