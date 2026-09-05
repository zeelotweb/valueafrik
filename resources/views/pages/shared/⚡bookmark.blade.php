<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A private "save for later" toggle, agnostic to what it's attached to —
 * the caller passes any model using the HasBookmarks concern (WallPost,
 * CommunityPost, ...). Unlike reactions, bookmarking is personal and
 * doesn't signal engagement to anyone else, so it doesn't earn Bridge
 * Score and shows no public count.
 */
new class extends Component {
    public Model $bookmarkable;

    #[Computed]
    public function bookmarked(): bool
    {
        return $this->bookmarkable->isBookmarkedBy(Auth::user());
    }

    public function toggle(): void
    {
        $user = Auth::user();

        $existing = $this->bookmarkable->bookmarks()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $this->bookmarkable->bookmarks()->create(['user_id' => $user->id]);
        }

        unset($this->bookmarked);
    }
}; ?>

<button
    type="button"
    wire:click="toggle"
    wire:loading.attr="disabled"
    wire:target="toggle"
    data-test="bookmark-toggle"
    title="{{ $this->bookmarked ? __('Remove bookmark') : __('Bookmark') }}"
    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium transition {{ $this->bookmarked ? '!text-amber-600 dark:!text-amber-400' : 'text-stone-500 hover:text-amber-600 dark:text-stone-400 dark:hover:text-amber-400' }}"
>
    <flux:icon.bookmark variant="{{ $this->bookmarked ? 'solid' : 'outline' }}" class="size-4" />
</button>
