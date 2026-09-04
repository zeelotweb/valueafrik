<?php

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create a community')] class extends Component {
    public string $name = '';
    public string $description = '';
    public string $visibility = Community::VISIBILITY_PUBLIC;
    public string $participation_level = Community::PARTICIPATION_POST;

    public function create()
    {
        abort_unless(Auth::user()->canCreateCommunity(), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,private,followers_only'],
            'participation_level' => ['required', 'in:post,view_only'],
        ]);

        $slug = $base = Str::slug($this->name) ?: 'community';
        $suffix = 1;

        while (Community::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$suffix;
        }

        $community = Auth::user()->ownedCommunities()->create([
            ...$validated,
            'slug' => $slug,
        ]);

        $community->members()->attach(Auth::id(), ['role' => 'owner', 'status' => 'active']);

        return $this->redirect(route('communities.show', $community), navigate: true);
    }

    public function with(): array
    {
        $user = Auth::user();

        return [
            'used' => $user->ownedCommunities()->count(),
            'limit' => $user->communitySlotLimit(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-xl">
    <flux:heading size="xl">{{ __('Create a community') }}</flux:heading>
    <flux:subheading>
        {{ __('You\'re using :used of :limit community slots.', ['used' => $used, 'limit' => $limit]) }}
    </flux:subheading>

    @if ($used >= $limit)
        <div class="mt-6 rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>
                {{ __('You\'ve used all your community slots. Grow your following to unlock more.') }}
            </flux:text>
        </div>
    @else
        <form wire:submit="create" class="mt-6 space-y-6">
            <flux:input wire:model="name" :label="__('Name')" placeholder="{{ __('e.g. Lagos Creatives') }}" />

            <flux:textarea wire:model="description" :label="__('Description')" rows="3" placeholder="{{ __('What is this community about?') }}" />

            <flux:radio.group wire:model="visibility" :label="__('Visibility')">
                <flux:radio value="public" :label="__('Public')" description="{{ __('Anyone can find and join.') }}" />
                <flux:radio value="followers_only" :label="__('Followers only')" description="{{ __('Only people who follow you can join.') }}" />
                <flux:radio value="private" :label="__('Private')" description="{{ __('Hidden from listings; join requests need your approval.') }}" />
            </flux:radio.group>

            <flux:radio.group wire:model="participation_level" :label="__('Participation')">
                <flux:radio value="post" :label="__('Anyone can post')" description="{{ __('All members can share posts.') }}" />
                <flux:radio value="view_only" :label="__('View only')" description="{{ __('Only you and monitors can post; members can view.') }}" />
            </flux:radio.group>

            <flux:button type="submit" variant="primary" color="green" wire:loading.attr="disabled">
                {{ __('Create community') }}
            </flux:button>
        </form>
    @endif
</div>
