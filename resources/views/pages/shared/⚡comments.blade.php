<?php

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The comment icon + count stays on the card; tapping it opens the full
 * thread in a modal instead of expanding inline. Agnostic to what it's
 * attached to — the caller passes any model using the HasComments concern
 * (WallPost, CommunityPost, ...).
 *
 * Threading (responses to a comment, replies to a response) isn't built
 * yet — this still renders a flat list. That's the next layer on top of
 * this modal, not a rewrite of it.
 */
new class extends Component {
    public Model $commentable;
    public bool $open = false;
    public string $body = '';

    public ?int $editingCommentId = null;
    public string $editBody = '';

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

    public function modalName(): string
    {
        return 'comments-'.str_replace('\\', '-', get_class($this->commentable)).'-'.$this->commentable->id;
    }

    public function openModal(): void
    {
        $this->open = true;

        $this->modal($this->modalName())->show();
    }

    public function closeModal(): void
    {
        $this->open = false;

        $this->reset(['editingCommentId', 'editBody']);
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

        unset($this->count, $this->comments);
    }

    public function startEdit(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);

        abort_if($comment->user_id !== Auth::id(), 403);

        $this->editingCommentId = $comment->id;
        $this->editBody = $comment->body;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingCommentId', 'editBody']);
    }

    public function update(): void
    {
        $comment = Comment::findOrFail($this->editingCommentId);

        abort_if($comment->user_id !== Auth::id(), 403);

        $this->validate(['editBody' => ['required', 'string', 'max:2000']]);

        $comment->update([
            'body' => $this->editBody,
            'edited_at' => now(),
        ]);

        $this->reset(['editingCommentId', 'editBody']);

        unset($this->comments);
    }

    public function delete(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);

        abort_if($comment->user_id !== Auth::id(), 403);

        $comment->delete();

        unset($this->count, $this->comments);
    }
}; ?>

<div class="min-w-0" wire:key="comments-{{ get_class($commentable) }}-{{ $commentable->id }}">
    <button
        type="button"
        wire:click="openModal"
        class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-stone-500 transition hover:text-cyan-600 dark:text-stone-400 dark:hover:text-cyan-400"
        data-test="comments-toggle"
    >
        <flux:icon.chat-bubble-left class="size-4" />
        @if ($this->count > 0)
            <span>{{ $this->count }}</span>
        @endif
    </button>

    <flux:modal name="{{ $this->modalName() }}" class="max-w-lg" wire:close="closeModal">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Comments') }}</flux:heading>

            <div class="max-h-[60vh] space-y-3 overflow-y-auto">
                @forelse ($this->comments as $comment)
                    <div class="flex items-start gap-2" wire:key="comment-{{ $comment->id }}">
                        <a href="{{ route('profile.show', $comment->user) }}" wire:navigate class="size-7 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                            @if ($comment->user->profile?->avatarUrl())
                                <img src="{{ $comment->user->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-500">
                                    <flux:icon.user class="size-3.5" />
                                </div>
                            @endif
                        </a>

                        <div class="min-w-0 flex-1 rounded-lg bg-stone-100 px-3 py-2 dark:bg-stone-800">
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('profile.show', $comment->user) }}" wire:navigate class="truncate text-sm font-medium text-stone-900 hover:underline dark:text-white">{{ $comment->user->name }}</a>
                                <div class="flex shrink-0 items-center gap-2">
                                    <span class="text-xs text-stone-400 dark:text-stone-500">
                                        {{ $comment->created_at->diffForHumans(null, true) }}
                                        @if ($comment->edited_at)
                                            &middot; {{ __('edited') }}
                                        @endif
                                    </span>
                                    @if ($comment->user_id === Auth::id() && $editingCommentId !== $comment->id)
                                        <button
                                            type="button"
                                            wire:click="startEdit({{ $comment->id }})"
                                            class="text-stone-400 hover:text-cyan-600 dark:hover:text-cyan-400"
                                        >
                                            <flux:icon.pencil class="size-3.5" />
                                        </button>
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

                            @if ($editingCommentId === $comment->id)
                                <form wire:submit="update" class="mt-1 space-y-1.5">
                                    <flux:textarea wire:model="editBody" rows="1" />
                                    @error('editBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                        <flux:button type="submit" size="sm" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="update">{{ __('Save') }}</flux:button>
                                    </div>
                                </form>
                            @else
                                <p class="mt-0.5 whitespace-pre-line text-sm text-stone-700 dark:text-stone-300">{{ $comment->body }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-400 dark:text-stone-500">{{ __('No comments yet — be the first.') }}</p>
                @endforelse
            </div>

            <form wire:submit="post" class="flex items-end gap-2 border-t border-stone-200 pt-4 dark:border-stone-800">
                <flux:textarea wire:model="body" rows="1" placeholder="{{ __('Write a comment…') }}" class="flex-1" />
                <flux:button type="submit" size="sm" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post">
                    {{ __('Post') }}
                </flux:button>
            </form>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </flux:modal>
</div>
