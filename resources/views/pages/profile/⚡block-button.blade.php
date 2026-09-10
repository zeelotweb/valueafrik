<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public User $user;
    public bool $overlay = false;

    #[Computed]
    public function isBlocked(): bool
    {
        return Auth::user()->hasBlocked($this->user);
    }

    public function toggle(): void
    {
        $viewer = Auth::user();

        abort_if($viewer->id === $this->user->id, 403);

        if ($this->isBlocked) {
            $viewer->unblock($this->user);

            Flux::toast(text: __(':name has been unblocked.', ['name' => $this->user->name]));
        } else {
            $viewer->block($this->user);

            Flux::toast(variant: 'success', text: __(':name has been blocked.', ['name' => $this->user->name]));
        }

        $this->redirect(route('profile.show', $this->user), navigate: true);
    }
}; ?>

<flux:dropdown position="bottom" align="end">
    <flux:button
        size="sm"
        variant="ghost"
        icon="ellipsis-vertical"
        class="{{ $overlay ? '!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900' : '' }}"
        aria-label="{{ __('More options') }}"
        data-test="profile-more-menu"
    />

    <flux:menu>
        <flux:menu.item
            wire:click="toggle"
            wire:confirm="{{ $this->isBlocked ? __('Unblock :name?', ['name' => $user->name]) : __('Block :name? They will no longer be able to follow or message you.', ['name' => $user->name]) }}"
            :icon="$this->isBlocked ? 'check-circle' : 'no-symbol'"
            variant="{{ $this->isBlocked ? 'default' : 'danger' }}"
            data-test="block-toggle-button"
        >
            {{ $this->isBlocked ? __('Unblock :name', ['name' => $user->name]) : __('Block :name', ['name' => $user->name]) }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
