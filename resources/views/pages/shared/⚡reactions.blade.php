<?php

use App\Models\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A single-tap "like" reaction, agnostic to what it's attached to — the
 * caller passes any model using the HasReactions concern (WallPost,
 * CommunityPost, Message, ...). One reaction per user per item; tapping
 * again removes it.
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

    public function toggle(): void
    {
        $user = Auth::user();

        $existing = $this->reactable->reactions()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $this->reactable->reactions()->create([
                'user_id' => $user->id,
                'type' => Reaction::TYPE_LIKE,
            ]);

            $user->awardBridgeScore('reaction_given', $this->reactable);
        }

        unset($this->count, $this->reacted);
    }
}; ?>

<button
    type="button"
    wire:click="toggle"
    wire:loading.attr="disabled"
    wire:target="toggle"
    data-test="reaction-toggle"
    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition {{ $this->reacted ? '!text-rose-600 dark:!text-rose-400' : 'text-stone-500 hover:text-rose-600 dark:text-stone-400 dark:hover:text-rose-400' }}"
>
    <flux:icon.heart variant="{{ $this->reacted ? 'solid' : 'outline' }}" class="size-4" />
    @if ($this->count > 0)
        <span>{{ $this->count }}</span>
    @endif
</button>
