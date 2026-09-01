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

        $conversation = Conversation::between($viewer, $this->user);

        if ($conversation->wasRecentlyCreated) {
            $viewer->awardBridgeScore('conversation_started', $conversation);
        }

        return $this->redirect(route('messages.show', $conversation), navigate: true);
    }
}; ?>

<flux:button
    wire:click="startConversation"
    wire:loading.attr="disabled"
    size="sm"
    variant="ghost"
    icon="chat-bubble-left-right"
    class="{{ $overlay ? '!bg-white/90 !text-stone-900 shadow-sm backdrop-blur hover:!bg-white dark:!bg-stone-900/80 dark:!text-white dark:hover:!bg-stone-900' : '' }}"
    data-test="message-button"
>
    <span class="hidden sm:inline">{{ __('Message') }}</span>
</flux:button>
