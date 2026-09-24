<?php

use App\Models\BridgePost;
use App\Notifications\BridgePostAccepted;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function accept(int $id): void
    {
        $bridgePost = BridgePost::findOrFail($id);

        abort_unless($bridgePost->partner_id === Auth::id(), 403);

        $bridgePost->update(['status' => BridgePost::STATUS_ACTIVE, 'responded_at' => now()]);

        SafeNotifier::send($bridgePost->initiator, new BridgePostAccepted($bridgePost));

        Flux::toast(variant: 'success', text: __('Bridge Post accepted — head to your Wall to add your side.'));
    }

    public function decline(int $id): void
    {
        $bridgePost = BridgePost::findOrFail($id);

        abort_unless($bridgePost->partner_id === Auth::id(), 403);

        $bridgePost->update(['status' => BridgePost::STATUS_DECLINED, 'responded_at' => now()]);

        Flux::toast(text: __('Bridge Post invite declined.'));
    }

    public function with(): array
    {
        return [
            'invites' => Auth::user()->bridgePostInvitesReceived()
                ->where('status', BridgePost::STATUS_PENDING)
                ->with('initiator')
                ->latest()
                ->get(),
        ];
    }
}; ?>

<div class="space-y-2" wire:key="bridge-post-invites">
    @forelse ($invites as $invite)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-score-400/40 p-4 dark:border-score-900" wire:key="invite-{{ $invite->id }}">
            <div class="min-w-0 flex-1">
                <p class="text-sm text-stone-900 dark:text-white">
                    {!! __(':name invited you to a Bridge Post on :theme', ['name' => '<span class="font-medium">'.e($invite->initiator->name).'</span>', 'theme' => '<span class="font-medium">'.e($invite->theme).'</span>']) !!}
                </p>
            </div>
            <div class="flex shrink-0 gap-2">
                <flux:button size="sm" variant="primary" wire:click="accept({{ $invite->id }})" class="!bg-score-600 hover:!bg-score-500">
                    {{ __('Accept') }}
                </flux:button>
                <flux:button size="sm" variant="ghost" wire:click="decline({{ $invite->id }})">
                    {{ __('Decline') }}
                </flux:button>
            </div>
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('No Bridge Post invites right now.') }}</flux:text>
        </div>
    @endforelse
</div>
