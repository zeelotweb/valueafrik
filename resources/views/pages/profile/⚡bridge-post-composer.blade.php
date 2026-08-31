<?php

use App\Models\BridgePost;
use App\Models\User;
use App\Notifications\BridgePostInvited;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $open = false;
    public string $theme = '';
    public string $partnerSearch = '';
    public ?int $partnerId = null;
    public ?string $partnerName = null;

    #[Computed]
    public function results()
    {
        if (mb_strlen($this->partnerSearch) < 2) {
            return collect();
        }

        return User::query()
            ->whereKeyNot(Auth::id())
            ->where('name', 'like', '%'.$this->partnerSearch.'%')
            ->limit(5)
            ->get();
    }

    public function pickPartner(int $userId, string $name): void
    {
        $this->partnerId = $userId;
        $this->partnerName = $name;
        $this->partnerSearch = '';
    }

    public function clearPartner(): void
    {
        $this->partnerId = null;
        $this->partnerName = null;
    }

    public function send(): void
    {
        $this->validate([
            'theme' => ['required', 'string', 'min:3', 'max:100'],
            'partnerId' => ['required', 'integer', 'exists:users,id'],
        ]);

        abort_if($this->partnerId === Auth::id(), 403);

        $bridgePost = BridgePost::create([
            'theme' => $this->theme,
            'initiator_id' => Auth::id(),
            'partner_id' => $this->partnerId,
            'status' => BridgePost::STATUS_PENDING,
        ]);

        $bridgePost->partner->notify(new BridgePostInvited($bridgePost));

        Flux::toast(variant: 'success', text: __('Bridge Post invite sent to :name.', ['name' => $this->partnerName]));

        $this->reset(['theme', 'partnerId', 'partnerName', 'partnerSearch', 'open']);
    }
}; ?>

<div class="mb-6">
    @if (! $open)
        <flux:button variant="ghost" wire:click="$set('open', true)" icon="arrows-right-left">
            {{ __('Start a Bridge Post') }}
        </flux:button>
    @else
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:subheading>{{ __('Invite someone to compare a shared tradition, side by side.') }}</flux:subheading>

            <flux:input wire:model="theme" :label="__('Theme')" placeholder="{{ __('e.g. Weddings, New Year, Sunday dinner') }}" class="mt-4" />

            <div class="mt-4">
                <flux:label>{{ __('Who are you inviting?') }}</flux:label>

                @if ($partnerId)
                    <div class="mt-2 flex items-center justify-between rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                        <span class="text-sm font-medium">{{ $partnerName }}</span>
                        <flux:button size="sm" variant="ghost" wire:click="clearPartner">{{ __('Change') }}</flux:button>
                    </div>
                @else
                    <flux:input wire:model.live.debounce.300ms="partnerSearch" icon="magnifying-glass" placeholder="{{ __('Search by name…') }}" class="mt-2" />

                    @if ($this->results->isNotEmpty())
                        <div class="mt-2 space-y-1 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                            @foreach ($this->results as $candidate)
                                <button
                                    type="button"
                                    wire:click="pickPartner({{ $candidate->id }}, '{{ addslashes($candidate->name) }}')"
                                    class="block w-full rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                >
                                    {{ $candidate->name }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif

                @error('partnerId') <p class="mt-1 text-sm text-red-600">{{ __('Pick someone to invite.') }}</p> @enderror
            </div>

            <div class="mt-4 flex gap-2">
                <flux:button wire:click="send" variant="primary" class="!bg-cyan-600 hover:!bg-cyan-500" wire:loading.attr="disabled">
                    {{ __('Send invite') }}
                </flux:button>
                <flux:button wire:click="$set('open', false)" variant="ghost">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
