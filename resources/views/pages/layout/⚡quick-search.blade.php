<?php

use Livewire\Component;

new class extends Component {}; ?>

<flux:dropdown position="bottom" align="end">
    <button
        type="button"
        class="flex items-center justify-center rounded-lg p-2 text-stone-600 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800"
        aria-label="{{ __('Search') }}"
    >
        <flux:icon.magnifying-glass class="size-5" />
    </button>

    <flux:menu>
        <flux:menu.item :href="route('discover.index')" icon="user-plus" wire:navigate>
            {{ __('Discover people') }}
        </flux:menu.item>
        <flux:menu.item :href="route('communities.index')" icon="user-group" wire:navigate>
            {{ __('Find a community') }}
        </flux:menu.item>
    </flux:menu>
</flux:dropdown>
