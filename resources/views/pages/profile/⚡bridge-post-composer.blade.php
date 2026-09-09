<?php

use App\Models\BridgePost;
use App\Models\User;
use App\Notifications\BridgePostInvited;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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

        SafeNotifier::send($bridgePost->partner, new BridgePostInvited($bridgePost));

        Flux::toast(variant: 'success', text: __('Bridge Post invite sent to :name.', ['name' => $this->partnerName]));

        $this->reset(['theme', 'partnerId', 'partnerName', 'partnerSearch']);

        $this->modal('bridge-post-composer')->close();
    }
}; ?>

<flux:modal name="bridge-post-composer" class="max-w-lg w-full">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Start a Bridge Post') }}</flux:heading>
            <flux:subheading>{{ __('Invite someone to compare a shared tradition, side by side.') }}</flux:subheading>
        </div>

        <flux:input wire:model="theme" :label="__('Theme')" placeholder="{{ __('e.g. Weddings, New Year, Sunday dinner') }}" />

        <div>
            <flux:label>{{ __('Who are you inviting?') }}</flux:label>

            @if ($partnerId)
                <div class="mt-2 flex items-center justify-between rounded-lg bg-white border border-stone-200 p-2 dark:bg-stone-900 dark:border-stone-800">
                    <span class="text-sm font-medium">{{ $partnerName }}</span>
                    <flux:button size="sm" variant="ghost" wire:click="clearPartner">{{ __('Change') }}</flux:button>
                </div>
            @else
                <flux:input wire:model.live.debounce.300ms="partnerSearch" icon="magnifying-glass" placeholder="{{ __('Search by name…') }}" class="mt-2" />

                @if ($this->results->isNotEmpty())
                    <div class="mt-2 space-y-1 rounded-lg bg-white border border-stone-200 p-2 dark:bg-stone-900 dark:border-stone-800">
                        @foreach ($this->results as $candidate)
                            <button
                                type="button"
                                wire:click="pickPartner({{ $candidate->id }}, '{{ addslashes($candidate->name) }}')"
                                class="block w-full rounded-md px-2 py-1.5 text-start text-sm hover:bg-stone-100 dark:hover:bg-stone-800"
                            >
                                {{ $candidate->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif

            @error('partnerId') <p class="mt-1 text-sm text-red-600">{{ __('Pick someone to invite.') }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button wire:click="send" variant="primary" color="cyan" wire:loading.attr="disabled">
                {{ __('Send invite') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
