<?php

use App\Models\Comment;
use App\Models\CommentVote;
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
 * Two levels: a top-level comment can have replies, opened in a second
 * modal stacked on the first. A reply itself isn't repliable — that's a
 * deliberate line for now, not a limitation of the schema (replies are
 * just comments with a parent_id, so a third level is a small extension
 * later if it's ever needed).
 */
new class extends Component {
    public Model $commentable;
    public bool $open = false;
    public string $body = '';

    public ?int $editingCommentId = null;
    public string $editBody = '';

    public ?int $viewingReplyFor = null;
    public string $replyBody = '';

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

        return $this->commentable->comments()
            ->whereNull('parent_id')
            ->withCount('replies')
            ->withCount(['votes as upvotes_count' => fn ($query) => $query->where('type', CommentVote::TYPE_UP)])
            ->withCount(['votes as downvotes_count' => fn ($query) => $query->where('type', CommentVote::TYPE_DOWN)])
            ->addSelect(['my_vote_type' => CommentVote::select('type')
                ->whereColumn('comment_id', 'comments.id')
                ->where('user_id', Auth::id())
                ->limit(1),
            ])
            ->with('user.profile')
            ->get();
    }

    #[Computed]
    public function replyParent(): ?Comment
    {
        return $this->viewingReplyFor
            ? Comment::with('user.profile')->find($this->viewingReplyFor)
            : null;
    }

    #[Computed]
    public function replies()
    {
        if (! $this->viewingReplyFor) {
            return collect();
        }

        return Comment::where('parent_id', $this->viewingReplyFor)
            ->withCount(['votes as upvotes_count' => fn ($query) => $query->where('type', CommentVote::TYPE_UP)])
            ->withCount(['votes as downvotes_count' => fn ($query) => $query->where('type', CommentVote::TYPE_DOWN)])
            ->addSelect(['my_vote_type' => CommentVote::select('type')
                ->whereColumn('comment_id', 'comments.id')
                ->where('user_id', Auth::id())
                ->limit(1),
            ])
            ->with('user.profile')
            ->latest()
            ->get();
    }

    public function modalName(): string
    {
        return 'comments-'.str_replace('\\', '-', get_class($this->commentable)).'-'.$this->commentable->id;
    }

    public function repliesModalName(): string
    {
        return 'replies-'.str_replace('\\', '-', get_class($this->commentable)).'-'.$this->commentable->id;
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

    public function openReplies(int $commentId): void
    {
        $this->viewingReplyFor = $commentId;

        $this->modal($this->repliesModalName())->show();
    }

    public function closeReplies(): void
    {
        $this->viewingReplyFor = null;

        $this->reset(['replyBody', 'editingCommentId', 'editBody']);
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

    public function postReply(): void
    {
        abort_unless($this->viewingReplyFor, 404);

        $this->validate(['replyBody' => ['required', 'string', 'max:2000']]);

        $parent = Comment::findOrFail($this->viewingReplyFor);

        $reply = $this->commentable->comments()->create([
            'user_id' => Auth::id(),
            'parent_id' => $parent->id,
            'body' => $this->replyBody,
        ]);

        Auth::user()->awardBridgeScore('comment_posted', $reply);

        $this->reset('replyBody');

        unset($this->count, $this->comments, $this->replies);
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

        unset($this->comments, $this->replies);
    }

    public function delete(int $commentId): void
    {
        $comment = Comment::findOrFail($commentId);

        abort_if($comment->user_id !== Auth::id(), 403);

        $comment->delete();

        unset($this->count, $this->comments, $this->replies);
    }

    public function voteUp(int $commentId): void
    {
        $this->vote($commentId, CommentVote::TYPE_UP);
    }

    public function voteDown(int $commentId): void
    {
        $this->vote($commentId, CommentVote::TYPE_DOWN);
    }

    private function vote(int $commentId, string $type): void
    {
        $comment = Comment::findOrFail($commentId);

        $comment->voteAs(Auth::user(), $type);

        unset($this->comments, $this->replies);
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

    {{-- Main thread: post preview pinned top, comments scroll in the middle, composer pinned bottom. --}}
    <flux:modal name="{{ $this->modalName() }}" class="max-w-lg p-0! max-lg:m-0! max-lg:h-dvh! max-lg:max-h-dvh! max-lg:w-full! max-lg:max-w-none! max-lg:rounded-none!" wire:close="closeModal">
        <div class="flex max-lg:h-full lg:max-h-[85vh] flex-col">
            <div class="shrink-0 border-b border-stone-200 p-4 pe-12 dark:border-stone-800">
                <flux:heading size="lg" class="mb-3">{{ __('Comments') }}</flux:heading>

                <div class="flex items-start gap-2">
                    <a href="{{ route('profile.show', $commentable->user) }}" wire:navigate class="size-8 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($commentable->user->profile?->avatarUrl())
                            <img src="{{ $commentable->user->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-4" />
                            </div>
                        @endif
                    </a>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('profile.show', $commentable->user) }}" wire:navigate class="truncate text-sm font-medium text-stone-900 hover:underline dark:text-white">{{ $commentable->user->name }}</a>
                            <span class="shrink-0 text-xs text-stone-400 dark:text-stone-500">{{ $commentable->created_at->diffForHumans() }}</span>
                        </div>

                        @if ($commentable->body)
                            @include('partials.clamped-text', ['text' => $commentable->body])
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-4">
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

                        <div class="min-w-0 flex-1">
                            <div class="rounded-lg bg-stone-100 px-3 py-2 dark:bg-stone-800">
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
                                            <button type="button" wire:click="startEdit({{ $comment->id }})" class="text-stone-400 hover:text-cyan-600 dark:hover:text-cyan-400">
                                                <flux:icon.pencil class="size-3.5" />
                                            </button>
                                            <button type="button" wire:click="delete({{ $comment->id }})" wire:confirm="{{ __('Delete this comment?') }}" class="text-stone-400 hover:text-red-600 dark:hover:text-red-400">
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

                            <div class="mt-1 ms-1 flex items-center gap-3">
                                @include('partials.comment-votes', ['comment' => $comment])

                                <button
                                    type="button"
                                    wire:click="openReplies({{ $comment->id }})"
                                    class="text-xs font-medium text-stone-500 transition hover:text-cyan-600 dark:text-stone-400 dark:hover:text-cyan-400"
                                    data-test="reply-button"
                                >
                                    {{ __('Reply') }}
                                    @if ($comment->replies_count > 0)
                                        &middot; {{ trans_choice('1 reply|:count replies', $comment->replies_count) }}
                                    @endif
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-stone-400 dark:text-stone-500">{{ __('No comments yet — be the first.') }}</p>
                @endforelse
            </div>

            <div class="shrink-0 border-t border-stone-200 p-3 dark:border-stone-800">
                <form wire:submit="post" class="flex items-end gap-2">
                    <flux:textarea wire:model="body" rows="1" placeholder="{{ __('Write a comment…') }}" class="flex-1" />
                    <flux:button type="submit" icon="paper-airplane" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="post" aria-label="{{ __('Send') }}" data-test="send-comment" />
                </form>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </flux:modal>

    {{-- Replies: the parent comment pinned top (same treatment as the post above), replies scroll in the middle, composer pinned bottom. --}}
    <flux:modal name="{{ $this->repliesModalName() }}" class="max-w-lg p-0! max-lg:m-0! max-lg:h-dvh! max-lg:max-h-dvh! max-lg:w-full! max-lg:max-w-none! max-lg:rounded-none!" wire:close="closeReplies">
        @if ($this->replyParent)
            <div class="flex max-lg:h-full lg:max-h-[85vh] flex-col">
                <div class="shrink-0 border-b border-stone-200 p-4 pe-12 dark:border-stone-800">
                    <flux:heading size="lg" class="mb-3">{{ __('Replies') }}</flux:heading>

                    <div class="flex items-start gap-2">
                        <a href="{{ route('profile.show', $this->replyParent->user) }}" wire:navigate class="size-8 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                            @if ($this->replyParent->user->profile?->avatarUrl())
                                <img src="{{ $this->replyParent->user->profile->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-500">
                                    <flux:icon.user class="size-4" />
                                </div>
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('profile.show', $this->replyParent->user) }}" wire:navigate class="truncate text-sm font-medium text-stone-900 hover:underline dark:text-white">{{ $this->replyParent->user->name }}</a>
                                <span class="shrink-0 text-xs text-stone-400 dark:text-stone-500">{{ $this->replyParent->created_at->diffForHumans() }}</span>
                            </div>

                            @include('partials.clamped-text', ['text' => $this->replyParent->body])
                        </div>
                    </div>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto p-4">
                    @forelse ($this->replies as $reply)
                        <div class="flex items-start gap-2" wire:key="reply-{{ $reply->id }}">
                            <a href="{{ route('profile.show', $reply->user) }}" wire:navigate class="size-7 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                                @if ($reply->user->profile?->avatarUrl())
                                    <img src="{{ $reply->user->profile->avatarUrl() }}" class="size-full object-cover">
                                @else
                                    <div class="flex size-full items-center justify-center text-stone-500">
                                        <flux:icon.user class="size-3.5" />
                                    </div>
                                @endif
                            </a>

                            <div class="min-w-0 flex-1">
                                <div class="rounded-lg bg-stone-100 px-3 py-2 dark:bg-stone-800">
                                    <div class="flex items-center justify-between gap-2">
                                        <a href="{{ route('profile.show', $reply->user) }}" wire:navigate class="truncate text-sm font-medium text-stone-900 hover:underline dark:text-white">{{ $reply->user->name }}</a>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="text-xs text-stone-400 dark:text-stone-500">
                                            {{ $reply->created_at->diffForHumans(null, true) }}
                                            @if ($reply->edited_at)
                                                &middot; {{ __('edited') }}
                                            @endif
                                        </span>
                                        @if ($reply->user_id === Auth::id() && $editingCommentId !== $reply->id)
                                            <button type="button" wire:click="startEdit({{ $reply->id }})" class="text-stone-400 hover:text-cyan-600 dark:hover:text-cyan-400">
                                                <flux:icon.pencil class="size-3.5" />
                                            </button>
                                            <button type="button" wire:click="delete({{ $reply->id }})" wire:confirm="{{ __('Delete this reply?') }}" class="text-stone-400 hover:text-red-600 dark:hover:text-red-400">
                                                <flux:icon.trash class="size-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if ($editingCommentId === $reply->id)
                                    <form wire:submit="update" class="mt-1 space-y-1.5">
                                        <flux:textarea wire:model="editBody" rows="1" />
                                        @error('editBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                                            <flux:button type="submit" size="sm" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="update">{{ __('Save') }}</flux:button>
                                        </div>
                                    </form>
                                @else
                                    <p class="mt-0.5 whitespace-pre-line text-sm text-stone-700 dark:text-stone-300">{{ $reply->body }}</p>
                                @endif
                            </div>

                            <div class="mt-1 ms-1">
                                @include('partials.comment-votes', ['comment' => $reply])
                            </div>
                        </div>
                    </div>
                    @empty
                        <p class="text-sm text-stone-400 dark:text-stone-500">{{ __('No replies yet — be the first.') }}</p>
                    @endforelse
                </div>

                <div class="shrink-0 border-t border-stone-200 p-3 dark:border-stone-800">
                    <form wire:submit="postReply" class="flex items-end gap-2">
                        <flux:textarea wire:model="replyBody" rows="1" placeholder="{{ __('Write a reply…') }}" class="flex-1" />
                        <flux:button type="submit" icon="paper-airplane" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="postReply" aria-label="{{ __('Send') }}" data-test="send-reply" />
                    </form>
                    @error('replyBody') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        @endif
    </flux:modal>
</div>
