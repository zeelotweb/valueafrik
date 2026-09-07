<?php

use App\Models\Heritage;
use App\Models\Interest;
use App\Models\Language;
use App\Support\Countries;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Roots')] class extends Component {
    public string $bio = '';
    public string $country = '';

    /** @var array<int, int> */
    public array $languageIds = [];

    /** @var array<int, int> */
    public array $heritageIds = [];

    /** @var array<int, int> */
    public array $interestIds = [];

    public string $newHeritageName = '';

    public string $newHeritageRegion = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->bio = $user->profile?->bio ?? '';
        $this->country = $user->profile?->country ?? '';
        $this->languageIds = $user->languages()->pluck('languages.id')->all();
        $this->heritageIds = $user->heritages()->pluck('heritages.id')->all();
        $this->interestIds = $user->interests()->pluck('interests.id')->all();
    }

    #[Computed]
    public function languages()
    {
        return Language::orderBy('name')->get();
    }

    #[Computed]
    public function heritages()
    {
        return Heritage::orderBy('name')->get();
    }

    #[Computed]
    public function regions(): array
    {
        return Heritage::REGIONS;
    }

    #[Computed]
    public function interests()
    {
        return Interest::orderBy('name')->get();
    }

    #[Computed]
    public function countries(): array
    {
        return Countries::all();
    }

    public function addHeritage(): void
    {
        $name = trim($this->newHeritageName);

        $this->validate([
            'newHeritageName' => ['required', 'string', 'max:255'],
            'newHeritageRegion' => ['required', 'string', 'in:'.implode(',', Heritage::REGIONS)],
        ]);

        $heritage = Heritage::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'region' => $this->newHeritageRegion]
        );

        // A sub-identity someone else already added (e.g. "Yoruba") may exist
        // without a region if it predates this field — fill it in now rather
        // than leaving it permanently unmatched by region-specific Culture
        // Sprint searches.
        if ($heritage->region === null) {
            $heritage->update(['region' => $this->newHeritageRegion]);
        }

        if (! in_array($heritage->id, $this->heritageIds, true)) {
            $this->heritageIds[] = $heritage->id;
        }

        $this->reset('newHeritageName', 'newHeritageRegion');
        unset($this->heritages);

        Flux::toast(variant: 'success', text: __(':name added to your Roots.', ['name' => $heritage->name]));
    }

    public function save(): void
    {
        $this->validate([
            'bio' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $user = Auth::user();

        $user->profile()->updateOrCreate([], [
            'bio' => $this->bio ?: null,
            'country' => $this->country ?: null,
        ]);

        $user->languages()->sync($this->languageIds);
        $user->heritages()->sync($this->heritageIds);
        $user->interests()->sync($this->interestIds);

        if (
            ! $user->hasEarnedBridgeScoreFor('roots_completed')
            && $this->bio !== ''
            && $this->languageIds !== []
            && $this->heritageIds !== []
        ) {
            $user->awardBridgeScore('roots_completed');
        }

        Flux::toast(variant: 'success', text: __('Your Roots have been updated.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Roots') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Roots')" :subheading="__('Your way of life — languages, heritage, and what you\'re curious about')">
        <form wire:submit="save" class="mt-6 space-y-8">

            <flux:textarea
                wire:model="bio"
                :label="__('Bio')"
                :placeholder="__('A short line about who you are and where you come from')"
                rows="3"
            />

            <flux:select wire:model="country" :label="__('Where you\'re based')" placeholder="{{ __('Select a country') }}">
                @foreach ($this->countries as $code => $name)
                    <flux:select.option value="{{ $code }}">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div x-data="{ search: '' }">
                <flux:label>{{ __('Languages you speak') }}</flux:label>
                <flux:input x-model="search" icon="magnifying-glass" :placeholder="__('Search languages…')" class="mt-2 mb-3" />
                <div class="max-h-56 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="languageIds" variant="pills" class="flex-wrap">
                        @foreach ($this->languages as $language)
                            <flux:checkbox
                                :value="$language->id"
                                :label="$language->name"
                                x-show="search === '' || {{ Js::from(Str::lower($language->name)) }}.includes(search.toLowerCase())"
                            />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>

            <div x-data="{ search: '' }">
                <flux:label>{{ __('Heritage') }}</flux:label>
                <flux:input x-model="search" icon="magnifying-glass" :placeholder="__('Search heritage…')" class="mt-2 mb-3" />
                <div class="max-h-56 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="heritageIds" variant="pills" class="flex-wrap">
                        @foreach ($this->heritages as $heritage)
                            <flux:checkbox
                                :value="$heritage->id"
                                :label="$heritage->name"
                                x-show="search === '' || {{ Js::from(Str::lower($heritage->name)) }}.includes(search.toLowerCase())"
                            />
                        @endforeach
                    </flux:checkbox.group>
                </div>
                <div class="mt-3 flex items-end gap-3">
                    <flux:input
                        wire:model="newHeritageName"
                        wire:keydown.enter.prevent="addHeritage"
                        :label="__('Not listed? Add your own')"
                        :placeholder="__('e.g. Yoruba')"
                        class="max-w-xs"
                    />
                    <flux:select wire:model="newHeritageRegion" :label="__('Which region is that part of?')" placeholder="{{ __('Choose a region…') }}" class="max-w-48">
                        @foreach ($this->regions as $option)
                            <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button wire:click="addHeritage" variant="ghost" data-test="add-heritage-button">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>

            <div x-data="{ search: '' }">
                <flux:label>{{ __('What are you curious about?') }}</flux:label>
                <flux:input x-model="search" icon="magnifying-glass" :placeholder="__('Search topics…')" class="mt-2 mb-3" />
                <div class="max-h-56 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="interestIds" variant="pills" class="flex-wrap">
                        @foreach ($this->interests as $interest)
                            <flux:checkbox
                                :value="$interest->id"
                                :label="$interest->name"
                                x-show="search === '' || {{ Js::from(Str::lower($interest->name)) }}.includes(search.toLowerCase())"
                            />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" color="cyan" type="submit" data-test="save-roots-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
