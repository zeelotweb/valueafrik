<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Messages')] class extends Component {
    public ?int $selectedConversationId = null;
    public string $search = '';

    /**
     * True only when this mounted via /messages/{conversation} — a direct
     * link from a "Message" button, a notification, or the mobile list
     * (see below). Controls which pane mobile shows: the list by default,
     * or — for a direct link — straight into that one thread, same as the
     * old standalone messages.show route used to, since there's no room
     * for both panes on a phone screen.
     */
    public bool $isDirectLink = false;

    public function mount(?Conversation $conversation = null): void
    {
        abort_unless(! $conversation || $conversation->participants->contains(Auth::id()), 403);

        $this->isDirectLink = $conversation !== null;

        // conversations.updated_at never bumps when a new message is sent
        // (Message has no $touches on Conversation), so ordering by it can
        // land on a stale thread instead of the one actually last active —
        // order by the latest message's timestamp instead, same signal the
        // list pane sorts by in with() below.
        $this->selectedConversationId = $conversation?->id
            ?? Auth::user()->conversations()
                ->whereHas('messages')
                ->withMax('messages', 'created_at')
                ->orderByDesc('messages_max_created_at')
                ->value('conversations.id');
    }

    public function select(int $conversationId): void
    {
        abort_unless(Auth::user()->conversations()->whereKey($conversationId)->exists(), 403);

        $this->selectedConversationId = $conversationId;
    }

    #[Computed]
    public function searchResults()
    {
        if (mb_strlen($this->search) < 2) {
            return collect();
        }

        $viewer = Auth::user();
        $blockedIds = $viewer->blocking()->pluck('users.id')->merge($viewer->blockedBy()->pluck('users.id'));

        return User::query()
            ->where('id', '!=', $viewer->id)
            ->whereNotIn('id', $blockedIds)
            ->where('name', 'like', "%{$this->search}%")
            ->with('profile')
            ->limit(5)
            ->get();
    }

    public function startConversationWith(int $userId): void
    {
        $viewer = Auth::user();
        $target = User::findOrFail($userId);

        abort_if($viewer->hasBlockRelationWith($target), 403);

        $conversation = Conversation::between($viewer, $target);

        $this->search = '';
        unset($this->searchResults);
        $this->selectedConversationId = $conversation->id;

        // Without this, mobile ends up with neither pane showing anything
        // new: the list pane's own visibility rule only hides for a direct
        // link, and the thread pane's only shows for one — so on a phone,
        // starting a conversation from search silently goes nowhere.
        $this->isDirectLink = true;
    }

    public function with(): array
    {
        $userId = Auth::id();

        // whereHas('messages') keeps a conversation someone started but never
        // actually wrote anything in out of the list — otherwise clicking
        // "Message" from a profile and then leaving would leave a permanent
        // empty thread cluttering the inbox. The thread pane can still show
        // the freshly-created one (see $selectedConversation below); it just
        // won't be highlighted in the list until the first real message.
        $conversations = Auth::user()->conversations()
            ->whereHas('messages')
            ->with([
                'latestMessage.media',
                'participants' => fn ($query) => $query->where('users.id', '!=', $userId)->with('profile'),
            ])
            ->get()
            ->sortByDesc(fn ($conversation) => $conversation->latestMessage?->created_at ?? $conversation->created_at)
            ->values();

        // A fresh, unconstrained fetch for the embedded thread component —
        // not $conversations->firstWhere(...), which has 'participants'
        // eager-loaded with the current user filtered OUT (for the list's
        // "who's the other person" display). Reusing that same instance
        // here would make the thread component's own mount() — which
        // checks participants->contains(Auth::id()) — always 403, since
        // the loaded relation would never contain the viewer.
        $selectedConversation = $this->selectedConversationId
            ? Conversation::find($this->selectedConversationId)
            : null;

        return [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-6xl">
    <flux:heading size="xl" class="font-display">{{ __('Messages') }}</flux:heading>

    <div class="surface-card mt-6 grid overflow-hidden lg:grid-cols-[320px_1fr]" style="height: 72vh">
        {{-- List pane — hidden on mobile only for a direct conversation link
             (see $isDirectLink), where there's no room to show both the
             list and the thread, so the thread wins. Always shown at lg+. --}}
        <div @class([
            'flex min-h-0 flex-col border-b border-stone-200 lg:border-r lg:border-b-0 dark:border-stone-800',
            'hidden lg:flex' => $isDirectLink,
        ])>
            <div class="border-b border-stone-200 p-3 dark:border-stone-800">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" size="sm" placeholder="{{ __('New message to…') }}" class="messages-input" />

                @if ($this->searchResults->isNotEmpty())
                    <div class="mt-2 space-y-0.5">
                        @foreach ($this->searchResults as $person)
                            <button
                                type="button"
                                wire:click="startConversationWith({{ $person->id }})"
                                class="flex w-full items-center gap-2 rounded-md p-2 text-start text-sm hover:bg-stone-100 dark:hover:bg-stone-800"
                            >
                                <span class="size-7 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                    @if ($person->profile?->avatarUrl())
                                        <img src="{{ $person->profile->avatarUrl() }}" class="size-full object-cover">
                                    @else
                                        <span class="flex size-full items-center justify-center text-stone-500"><flux:icon.user class="size-3.5" /></span>
                                    @endif
                                </span>
                                <span class="truncate font-medium text-stone-700 dark:text-stone-300">{{ $person->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="scrollbar-thin flex-1 space-y-0.5 overflow-y-auto p-2">
                @forelse ($conversations as $conversation)
                    @php
                        $other = $conversation->participants->first();
                        $lastRead = $conversation->pivot->last_read_at;
                        $last = $conversation->latestMessage;
                        $isUnread = $last && $last->user_id !== Auth::id() && (! $lastRead || $lastRead->lt($last->created_at));
                        $preview = $last
                            ? ($last->body ?: ($last->media->isNotEmpty() ? __('📷 Photo') : ''))
                            : __('No messages yet');
                        $isActive = $conversation->id === $selectedConversation?->id;
                    @endphp

                    {{-- Desktop: updates the thread pane in place via Livewire.
                         Mobile (below lg, no room for two panes): the
                         preventDefault never fires, so this is a normal link
                         to the real full-page thread — the standard
                         responsive pattern (master-detail vs. full-screen
                         push). --}}
                    <a
                        href="{{ route('messages.show', $conversation) }}"
                        wire:navigate
                        x-data
                        x-on:click="if (window.innerWidth >= 1024) { $event.preventDefault(); $wire.select({{ $conversation->id }}) }"
                        @class([
                            'flex items-center gap-3 rounded-md p-2.5',
                            'bg-messages-50 dark:bg-messages-950' => $isActive,
                            'hover:bg-stone-100 dark:hover:bg-stone-800' => ! $isActive,
                        ])
                    >
                        <span class="size-10 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                            @if ($other?->profile?->avatarUrl())
                                <img src="{{ $other->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <span class="flex size-full items-center justify-center text-stone-500"><flux:icon.user class="size-4" /></span>
                            @endif
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium {{ $isUnread ? 'text-stone-900 dark:text-white' : 'text-stone-700 dark:text-stone-300' }}">
                                    {{ $other?->name ?? __('Unknown') }}
                                </span>
                                @if ($last)
                                    <span class="shrink-0 text-xs text-stone-400">{{ $last->created_at->diffForHumans(null, true) }}</span>
                                @endif
                            </span>
                            <span class="block truncate text-xs {{ $isUnread ? 'font-medium text-stone-900 dark:text-white' : 'text-stone-500 dark:text-stone-400' }}">
                                {{ $preview }}
                            </span>
                        </span>

                        @if ($isUnread)
                            <span class="size-2 shrink-0 rounded-full bg-messages-600 dark:bg-messages-400"></span>
                        @endif
                    </a>
                @empty
                    <div class="p-4 text-center">
                        <flux:text class="text-sm">{{ __("You don't have any messages yet — search above to start one.") }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Thread pane — hidden on mobile by default (the list is the
             point of the page there), except for a direct conversation
             link, where the thread IS the page — mobile has nowhere else
             to show it. --}}
        <div @class([
            'min-h-0 lg:flex lg:flex-col',
            'flex' => $isDirectLink,
            'hidden lg:flex' => ! $isDirectLink,
        ])>
            @if ($selectedConversation)
                <livewire:pages::messages.show :conversation="$selectedConversation" :key="'inbox-thread-'.$selectedConversation->id" />
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-stone-400 dark:text-stone-600">
                    {{ __('Select a conversation, or search above to start one.') }}
                </div>
            @endif
        </div>
    </div>
</div>
