<?php

use App\Models\BugReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Mounted once, globally, in the app shell (persisted across wire:navigate,
 * same as the incoming-call ringer) — a tester should be able to flag
 * something no matter what page it happened on.
 */
new class extends Component {
    public bool $open = false;

    public string $description = '';

    public function submit(string $url): void
    {
        $this->validate(['description' => ['required', 'string', 'max:2000']]);

        BugReport::create([
            'user_id' => Auth::id(),
            'url' => $url,
            'description' => $this->description,
        ]);

        $this->reset('description', 'open');

        Flux::toast(variant: 'success', text: __('Thanks — the team can see it now.'));
    }
}; ?>

{{--
    Right, not left — the sidebar's own profile/avatar trigger sits at
    bottom-left in the persistent app layout, and this used to sit directly
    on top of it. bottom-20 (not bottom-4) clears the toast group, which
    also renders bottom-right ("bottom end") — confirmed live that bottom-4
    put this right where a toast appears, hiding it for the few seconds
    the toast is visible.
--}}
<div class="fixed bottom-20 right-4 z-40" x-data="{ open: @entangle('open') }">
    <button
        type="button"
        x-show="!open"
        x-cloak
        x-on:click="open = true"
        class="flex items-center gap-2 rounded-full bg-stone-900 px-4 py-2.5 text-sm font-medium text-white shadow-lg hover:bg-stone-800 dark:bg-white dark:text-stone-900 dark:hover:bg-stone-200"
        data-test="report-bug-button"
    >
        <flux:icon.bug-ant class="size-4" />
        {{ __('Report a bug') }}
    </button>

    <div x-show="open" x-cloak x-on:click.outside="open = false" class="surface-card w-72 p-4 shadow-xl">
        <div class="flex items-center justify-between">
            <flux:heading size="sm">{{ __('Report a bug') }}</flux:heading>
            <button type="button" x-on:click="open = false" class="text-stone-400 hover:text-stone-600 dark:hover:text-stone-200" aria-label="{{ __('Close') }}">
                <flux:icon.x-mark class="size-4" />
            </button>
        </div>

        <flux:textarea
            wire:model="description"
            :placeholder="__('What went wrong? The more detail, the better.')"
            rows="4"
            class="mt-2"
        />
        @error('description') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror

        <flux:button variant="primary" class="btn-flat-primary mt-3 w-full" x-on:click="$wire.submit(window.location.href)">
            {{ __('Send') }}
        </flux:button>
    </div>
</div>
