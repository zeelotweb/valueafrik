<?php

use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The dedicated "heart" reaction — a quick-tap shortcut for
 * Reaction::TYPE_LIKE, agnostic to what it's attached to (any model using
 * HasReactions). Shares one reaction-per-user row with the emoji picker
 * (shared/emoji-reactions), so picking an emoji there replaces this one
 * and vice versa — both sides listen for the same event to stay in sync.
 */
new class extends Component {
    public Model $reactable;

    #[Computed]
    public function count(): int
    {
        return $this->reactable->reactionsCount();
    }

    #[Computed]
    public function reacted(): bool
    {
        return $this->reactable->isReactedBy(Auth::user());
    }

    #[Computed]
    public function myType(): ?string
    {
        return $this->reactable->myReactionType(Auth::user());
    }

    #[On('reaction-changed')]
    public function refresh(): void
    {
        unset($this->count, $this->reacted, $this->myType);
    }

    public function toggle(): void
    {
        $this->reactable->reactAs(Auth::user(), Reaction::TYPE_LIKE);

        $this->refresh();

        $this->dispatch('reaction-changed');
    }
}; ?>

<?php $isLiked = $this->myType === Reaction::TYPE_LIKE; ?>
<button
    type="button"
    wire:click="toggle"
    wire:loading.attr="disabled"
    wire:target="toggle"
    data-test="reaction-toggle"
    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition {{ $isLiked ? '!text-rose-600 dark:!text-rose-400' : 'text-stone-500 hover:text-rose-600 dark:text-stone-400 dark:hover:text-rose-400' }}"
>
    <flux:icon.heart variant="{{ $isLiked ? 'solid' : 'outline' }}" class="size-4" />
    @if ($this->count > 0)
        <span>{{ $this->count }}</span>
    @endif
</button>
