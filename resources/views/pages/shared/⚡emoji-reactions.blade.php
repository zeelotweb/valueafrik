<?php

use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The emoji reaction picker — an alternate to the heart (shared/reactions)
 * over the same one-reaction-per-user row. Picking an emoji here replaces
 * whatever reaction (including a heart) the user already had; tapping the
 * same emoji again removes it.
 */
new class extends Component {
    public Model $reactable;

    #[Computed]
    public function count(): int
    {
        return $this->reactable->reactionsCount();
    }

    #[Computed]
    public function myType(): ?string
    {
        return $this->reactable->myReactionType(Auth::user());
    }

    /**
     * Only meaningful when it's one of the picker's own options — the
     * heart button owns displaying TYPE_LIKE.
     */
    #[Computed]
    public function myEmoji(): ?string
    {
        $type = $this->myType;

        return ($type && in_array($type, Reaction::EMOJIS, true)) ? $type : null;
    }

    #[On('reaction-changed')]
    public function refresh(): void
    {
        unset($this->count, $this->myType, $this->myEmoji);
    }

    public function react(string $emoji): void
    {
        abort_unless(in_array($emoji, Reaction::EMOJIS, true), 422);

        $this->reactable->reactAs(Auth::user(), $emoji);

        $this->refresh();

        $this->dispatch('reaction-changed');
    }
}; ?>

<div x-data="{ open: false }" class="relative">
    <button
        type="button"
        x-on:click="open = ! open"
        data-test="emoji-reaction-trigger"
        class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition {{ $this->myEmoji ? '!text-amber-600 dark:!text-amber-400' : 'text-stone-500 hover:text-amber-600 dark:text-stone-400 dark:hover:text-amber-400' }}"
    >
        <flux:icon.face-smile variant="outline" class="size-4" />
        @if ($this->count > 0)
            <span>{{ $this->count }}</span>
        @endif
        @if ($this->myEmoji)
            <span class="text-base leading-none">{{ $this->myEmoji }}</span>
        @endif
    </button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-transition
        style="display: none;"
        class="absolute bottom-full start-0 z-10 mb-2 flex items-center gap-1 rounded-full bg-white border border-stone-200 p-1.5 shadow-lg dark:bg-stone-900 dark:border-stone-800"
    >
        @foreach (\App\Models\Reaction::EMOJIS as $emoji)
            <button
                type="button"
                wire:click="react('{{ $emoji }}')"
                x-on:click="open = false"
                data-test="emoji-option-{{ $loop->index }}"
                class="flex size-8 items-center justify-center rounded-full text-lg leading-none transition hover:scale-125 hover:bg-stone-100 dark:hover:bg-stone-800 {{ $this->myEmoji === $emoji ? 'bg-amber-50 dark:bg-amber-950/50' : '' }}"
            >
                {{ $emoji }}
            </button>
        @endforeach
    </div>
</div>
