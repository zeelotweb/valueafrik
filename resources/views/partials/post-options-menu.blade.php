{{--
    Shared "..." menu for a post card — consolidates what used to be a row
    of separate icon buttons (report/edit/delete) into one dropdown, plus
    adds Share/Hide/Mute/Block. The host component (communities::posts,
    profile::wall-posts, ...) must define: delete($postId), startReport
    ($postId), hidePost($postId), toggleMute($postId), toggleBlock($postId).

    Expects: $post, $isMine (bool), $canDelete (bool), $shareUrl (string),
    $editDispatchEvent (string, only used when $isMine).
--}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Post options') }}" />

    <flux:menu>
        <flux:menu.item
            x-data="{ copied: false }"
            x-on:click="navigator.clipboard.writeText(@js($shareUrl)); copied = true; setTimeout(() => copied = false, 1500)"
            icon="link"
        >
            <span x-show="! copied">{{ __('Share') }}</span>
            <span x-show="copied" x-cloak>{{ __('Link copied!') }}</span>
        </flux:menu.item>

        @if ($isMine)
            <flux:menu.item wire:click="$dispatch('{{ $editDispatchEvent }}', { postId: {{ $post->id }} })" icon="pencil">
                {{ __('Edit') }}
            </flux:menu.item>
        @else
            <flux:menu.item wire:click="hidePost({{ $post->id }})" icon="eye-slash">
                {{ __('Hide this post') }}
            </flux:menu.item>

            <flux:menu.item
                wire:click="toggleMute({{ $post->id }})"
                icon="{{ Auth::user()->hasMuted($post->user) ? 'speaker-wave' : 'speaker-x-mark' }}"
            >
                {{ Auth::user()->hasMuted($post->user) ? __('Unmute :name', ['name' => $post->user->name]) : __('Mute :name', ['name' => $post->user->name]) }}
            </flux:menu.item>

            <flux:menu.item wire:click="startReport({{ $post->id }})" icon="flag">
                {{ __('Report') }}
            </flux:menu.item>

            <flux:menu.item
                wire:click="toggleBlock({{ $post->id }})"
                wire:confirm="{{ Auth::user()->hasBlocked($post->user) ? __('Unblock :name?', ['name' => $post->user->name]) : __('Block :name? They will no longer be able to follow or message you.', ['name' => $post->user->name]) }}"
                icon="{{ Auth::user()->hasBlocked($post->user) ? 'check-circle' : 'no-symbol' }}"
                variant="{{ Auth::user()->hasBlocked($post->user) ? 'default' : 'danger' }}"
            >
                {{ Auth::user()->hasBlocked($post->user) ? __('Unblock :name', ['name' => $post->user->name]) : __('Block :name', ['name' => $post->user->name]) }}
            </flux:menu.item>
        @endif

        @if ($canDelete)
            <flux:menu.item
                wire:click="delete({{ $post->id }})"
                wire:confirm="{{ __('Delete this post?') }}"
                icon="trash"
                variant="danger"
            >
                {{ __('Delete') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
