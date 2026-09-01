<?php

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Community')] class extends Component {
    public Community $community;

    public function mount(Community $community): void
    {
        abort_unless($community->canView(Auth::user()), 403);

        $this->community = $community;
    }

    #[On('community-membership-changed')]
    public function refreshMemberCount(): void
    {
        //
    }

    public function with(): array
    {
        $this->community->loadCount('activeMembers');

        return [];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <div class="relative">
        <div
            class="h-40 w-full overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 bg-cover bg-center dark:border-zinc-700 dark:bg-zinc-800"
            @if ($community->coverUrl()) style="background-image: url('{{ $community->coverUrl() }}')" @endif
        ></div>

        <div class="absolute -bottom-8 start-6">
            <div class="size-20 overflow-hidden rounded-2xl border-4 border-white bg-zinc-200 shadow-sm dark:border-zinc-950 dark:bg-zinc-700">
                @if ($community->avatarUrl())
                    <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                @else
                    <div class="flex size-full items-center justify-center text-zinc-500">
                        <flux:icon.user-group class="size-8" />
                    </div>
                @endif
            </div>
        </div>

        <div class="absolute top-3 end-3 sm:top-4 sm:end-4">
            @if (Auth::id() === $community->owner_id)
                <flux:button
                    :href="route('communities.edit', $community)"
                    wire:navigate
                    size="sm"
                    variant="ghost"
                    icon="pencil"
                    class="!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900"
                >
                    <span class="hidden sm:inline">{{ __('Edit community') }}</span>
                </flux:button>
            @else
                <livewire:pages::communities.join-button :community="$community" :key="'join-'.$community->id" :overlay="true" />
            @endif
        </div>
    </div>

    <div class="mt-10 px-2">
        <div class="flex items-center gap-2">
            <flux:heading size="xl">{{ $community->name }}</flux:heading>
            @if ($community->visibility !== 'public')
                <flux:badge size="sm" color="zinc">
                    {{ $community->visibility === 'private' ? __('Private') : __('Followers only') }}
                </flux:badge>
            @endif
        </div>

        @if ($community->description)
            <p class="mt-2 max-w-xl text-zinc-700 dark:text-zinc-300">{{ $community->description }}</p>
        @endif

        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            {{ trans_choice('1 member|:count members', $community->active_members_count) }}
            &middot;
            {{ __('owned by') }} {{ $community->owner->name }}
        </p>

        <div class="mt-8">
            <livewire:pages::communities.composer :community="$community" :key="'composer-'.$community->id" />

            <livewire:pages::communities.posts :community="$community" :key="'posts-'.$community->id" />
        </div>

        <livewire:pages::communities.members :community="$community" :key="'members-'.$community->id" />
    </div>
</div>
