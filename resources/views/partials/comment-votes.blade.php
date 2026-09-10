@php
    $myVote = $comment->myVote(auth()->user());
@endphp
<div class="flex items-center gap-3">
    <button
        type="button"
        wire:click="voteUp({{ $comment->id }})"
        data-test="upvote-button"
        class="flex items-center gap-1 text-xs font-medium transition {{ $myVote === \App\Models\CommentVote::TYPE_UP ? '!text-green-600 dark:!text-green-400' : 'text-stone-500 hover:text-green-600 dark:text-stone-400 dark:hover:text-green-400' }}"
    >
        <flux:icon.hand-thumb-up variant="{{ $myVote === \App\Models\CommentVote::TYPE_UP ? 'solid' : 'outline' }}" class="size-3.5" />
        @if ($comment->upvotesCount() > 0)
            <span>{{ $comment->upvotesCount() }}</span>
        @endif
    </button>

    <button
        type="button"
        wire:click="voteDown({{ $comment->id }})"
        data-test="downvote-button"
        class="flex items-center gap-1 text-xs font-medium transition {{ $myVote === \App\Models\CommentVote::TYPE_DOWN ? '!text-red-600 dark:!text-red-400' : 'text-stone-500 hover:text-red-600 dark:text-stone-400 dark:hover:text-red-400' }}"
    >
        <flux:icon.hand-thumb-down variant="{{ $myVote === \App\Models\CommentVote::TYPE_DOWN ? 'solid' : 'outline' }}" class="size-3.5" />
        @if ($comment->downvotesCount() > 0)
            <span>{{ $comment->downvotesCount() }}</span>
        @endif
    </button>
</div>
