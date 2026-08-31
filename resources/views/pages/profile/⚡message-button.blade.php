<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public User $user;

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
    data-test="message-button"
>
    {{ __('Message') }}
</flux:button>
