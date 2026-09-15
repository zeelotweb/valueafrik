<?php

use App\Models\User;
use App\Notifications\NewFollower;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /**
     * Caps follow/unfollow toggles per viewer-target pair per minute —
     * every follow sends the target a notification, so without this a
     * follow/unfollow/follow loop can spam someone's notification feed
     * indefinitely even though the Bridge Score award itself is deduped.
     */
    private const MAX_TOGGLES_PER_MINUTE = 6;

    public User $user;
    public bool $overlay = false;
    public bool $iconOnly = false;

    #[Computed]
    public function isFollowing(): bool
    {
        return Auth::user()->isFollowing($this->user);
    }

    #[On('follow-toggled')]
    public function refresh(): void
    {
        unset($this->isFollowing);
    }

    public function toggle(): void
    {
        $viewer = Auth::user();

        abort_if($viewer->id === $this->user->id, 403);

        $throttleKey = 'follow-toggle:'.$viewer->id.':'.$this->user->id;
        abort_if(RateLimiter::tooManyAttempts($throttleKey, self::MAX_TOGGLES_PER_MINUTE), 429);
        RateLimiter::hit($throttleKey, 60);

        if ($this->isFollowing) {
            $viewer->following()->detach($this->user->id);

            Flux::toast(text: __('You unfollowed :name.', ['name' => $this->user->name]));
        } else {
            abort_if($viewer->hasBlockRelationWith($this->user), 403);

            try {
                $viewer->following()->attach($this->user->id);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // A concurrent request already recorded this follow — the
                // desired state is reached either way, so stop here rather
                // than risk a duplicate award below.
                unset($this->isFollowing);

                return;
            }

            // Scoped to this specific pair — unfollowing never claws the
            // point back, so without this guard, follow/unfollow/follow
            // repeatedly re-awards every time. Once earned for this pair it
            // stays earned; it just doesn't re-earn.
            if (! $viewer->hasEarnedBridgeScoreFor('follow', $this->user)) {
                $viewer->awardBridgeScore('follow', $this->user);

                if ($viewer->isCrossHeritageWith($this->user)) {
                    $viewer->awardBridgeScore('follow_cross_heritage_bonus', $this->user);
                }
            }

            if (! $this->user->hasEarnedBridgeScoreFor('followed_by_someone', $viewer)) {
                $this->user->awardBridgeScore('followed_by_someone', $viewer);
            }

            SafeNotifier::send($this->user, new NewFollower($viewer));

            Flux::toast(variant: 'success', text: __('You are now following :name.', ['name' => $this->user->name]));
        }

        unset($this->isFollowing);

        $this->dispatch('follow-toggled');
    }
}; ?>

<?php
    if ($iconOnly) {
        $variant = 'ghost';
        $class = $this->isFollowing
            ? '!text-stone-400 hover:!text-stone-600 hover:!bg-stone-100 dark:!text-stone-500 dark:hover:!text-stone-300 dark:hover:!bg-stone-800'
            : '!text-stone-900 hover:!text-stone-700 hover:!bg-stone-100 dark:!text-white dark:hover:!bg-stone-800';
    } else {
        $variant = $this->isFollowing ? 'ghost' : 'primary';
        $class = $this->isFollowing
            ? ($overlay ? 'btn-overlay-neutral' : '')
            : ($overlay ? 'btn-overlay-primary' : 'btn-flat-primary');
        // The trailing text is only hidden via CSS at sm+ ("hidden sm:inline"),
        // not actually removed from the slot — Flux only treats a button as
        // square (icon centered, no text padding) when the slot is truly
        // empty, so without this the icon sits off-center below sm.
        $class .= ' max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!';
    }
?>
<flux:button
    wire:click="toggle"
    wire:loading.attr="disabled"
    size="sm"
    variant="{{ $variant }}"
    icon="{{ $this->isFollowing ? 'check' : 'user-plus' }}"
    class="{{ $class }}"
    :tooltip="$iconOnly ? ($this->isFollowing ? __('Following') : __('Follow')) : null"
    aria-label="{{ $this->isFollowing ? __('Following') : __('Follow') }}"
    data-test="follow-button"
>
    @unless ($iconOnly)
        <span class="hidden sm:inline">{{ $this->isFollowing ? __('Following') : __('Follow') }}</span>
    @endunless
</flux:button>
