<?php

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * A collapsible comment thread, agnostic to what it's attached to — the
 * caller passes any model using the HasComments concern (WallPost,
 * CommunityPost, ...). Collapsed by default so feeds stay compact; opens
 * on tap to reveal the thread and a composer.
 */
new class extends Component {
    public Model $commentable;
    public bool $open = false;
    public string $body = '';

    #[Computed]
    public function count(): int
    {
        return $this->commentable->commentsCount();
    }

    #[Computed]
    public function comments()
    {
        if (! $this->open) {
            return collect();
        }

        return $this->commentable->comments()->with('user.profile')->get();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function post(): void
    {
        $this->validate(['body' => ['required', 'string', 'max:2000']]);

        $comment = $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'body' => $this->body,
        ]);

        Auth::user()->awardBridgeScore('comment_posted', $comment);

        $this->reset('body');
        $this->open = true;

        unset($this->count, $this->comments);
    }

    public function delete(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);

        abort_if($comment->user_id !== Auth::id(), 403);

        $comment->delete();

        unset($this->count, $this->comments);
    }
}; ?>

<div class="min-w-0 flex-1" wire:key="comments-{{ get_class($commentable) }}-{{ $commentable->id }}">
    <button
        type="button"
        wire:click="toggle"
        class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-stone-500 transition hover:text-cyan-600 dark:text-stone-400 dark:hover:text-cyan-400"
        data-test="comments-toggle"
    >
        <flux:icon.chat-bubble-left class="size-4" />
        {{ $this->count > 0 ? trans_choice('1 comment|:count comments', $this->count) : __('Comment') }}
    </button>

    @if ($open)
        <div class="mt-3 space-y-3 border-t border-stone-200 pt-3 dark:border-stone-800">
            @forelse ($this->comments as $comment)
                <div class="flex items-start gap-2" wire:key="comment-{{ $comment->id }}">
                    <div class="size-7 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($comment->user->profile?->avatarUrl())
                            <img src="{{ $comment->user->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-3.5" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 rounded-lg bg-stone-100 px-3 py-2 dark:bg-stone-800">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium text-stone-900 dark:text-white">{{ $comment->user->name }}</span>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-xs text-stone-400 dark:text-stone-500">{{ $comment->created_at->diffForHumans(null, true) }}</span>
                                @if ($comment->user_id === Auth::id())
                                    <button
                                        type="button"
                                        wire:click="delete({{ $comment->id }})"
                                        wire:confirm="{{ __('Delete this comment?') }}"
                                        class="text-stone-400 hover:text-red-600 dark:hover:text-red-400"
                                    >
                                        <flux:icon.trash class="size-3.5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                        <p class="mt-0.5 whitespace-pre-line text-sm text-stone-700 dark:text-stone-300">{{ $comment->body }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-400 dark:text-stone-500">{{ __('No comments yet — be the first.') }}</p>
            @endforelse

            <form wire:submit="post" class="flex items-end gap-2">
                <flux:textarea wire:model="body" rows="1" placeholder="{{ __('Write a comment…') }}" class="flex-1" />
                <flux:button type="submit" size="sm" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post">
                    {{ __('Post') }}
                </flux:button>
            </form>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    @endif
</div>
