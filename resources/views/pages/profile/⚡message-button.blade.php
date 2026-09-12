<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public User $user;
    public bool $overlay = false;

    public function startConversation()
    {
        $viewer = Auth::user();

        abort_if($viewer->id === $this->user->id, 403);
        abort_if($viewer->hasBlockRelationWith($this->user), 403);

        $conversation = Conversation::between($viewer, $this->user);

        if ($conversation->wasRecentlyCreated) {
            $viewer->awardBridgeScore('conversation_started', $conversation);
        }

        return $this->redirect(route('messages.show', $conversation), navigate: true);
    }
}; ?>

<div>
    @unless (Auth::user()->hasBlockRelationWith($user))
        <flux:button
            wire:click="startConversation"
            wire:loading.attr="disabled"
            size="sm"
            variant="ghost"
            icon="chat-bubble-left-right"
            class="{{ $overlay ? '!bg-violet-50/90 !text-violet-800 shadow-sm backdrop-blur hover:!bg-violet-50 dark:!bg-violet-950/80 dark:!text-violet-300 dark:hover:!bg-violet-950' : '' }} max-sm:w-8! max-sm:gap-0! max-sm:ps-0! max-sm:pe-0!"
            data-test="message-button"
        >
            <span class="hidden sm:inline">{{ __('Message') }}</span>
        </flux:button>
    @endunless
</div>
