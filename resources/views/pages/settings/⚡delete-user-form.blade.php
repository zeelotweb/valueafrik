<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 rounded-xl border border-red-200 bg-red-50/40 p-5 dark:border-red-900/50 dark:bg-red-950/10">
    <div class="flex items-center gap-2 text-red-700 dark:text-red-400">
        <flux:icon.exclamation-triangle class="size-4.5" />
        <flux:heading class="text-red-700 dark:text-red-400">{{ __('Danger zone') }}</flux:heading>
    </div>

    <flux:subheading class="mt-1">{{ __('Delete your account and all of its resources — this cannot be undone.') }}</flux:subheading>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" class="mt-4" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
