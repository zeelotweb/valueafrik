<?php

use App\Models\Community;
use App\Models\Heritage;
use App\Models\Interest;
use App\Models\Language;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A short, unskippable-by-accident wizard shown once, right after email
 * verification — the single hook point every signup path (password,
 * Google, Facebook) funnels through via EnsureOnboardingComplete. Tackles
 * the cold-start problem directly: a brand new profile with no Roots, no
 * follows, and no communities has nothing to look at on the dashboard.
 */
new #[Title('Welcome')] class extends Component {
    public int $step = 1;

    public string $bio = '';
    public string $country = '';

    /** @var array<int, int> */
    public array $languageIds = [];

    /** @var array<int, int> */
    public array $heritageIds = [];

    /** @var array<int, int> */
    public array $interestIds = [];

    public function mount(): void
    {
        if (Auth::user()->hasCompletedOnboarding()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
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
    public function interests()
    {
        return Interest::orderBy('name')->get();
    }

    #[Computed]
    public function countries(): array
    {
        return Countries::all();
    }

    /**
     * Same shape as Discover's own suggestions, just capped smaller — this
     * is a first taste, not the full page.
     */
    #[Computed]
    public function suggestedPeople()
    {
        $viewer = Auth::user();
        $excluded = $viewer->following()->pluck('users.id')->push($viewer->id);

        $people = collect();

        if ($this->interestIds !== []) {
            $people = User::query()
                ->whereKeyNot($excluded)
                ->whereHas('interests', fn ($query) => $query->whereIn('interests.id', $this->interestIds))
                ->withCount(['interests as shared_interests_count' => fn ($query) => $query->whereIn('interests.id', $this->interestIds)])
                ->with(['profile', 'heritages'])
                ->orderByDesc('shared_interests_count')
                ->limit(6)
                ->get();
        }

        if ($people->count() < 6) {
            $more = User::query()
                ->whereKeyNot($excluded->merge($people->pluck('id')))
                ->with(['profile', 'heritages'])
                ->latest()
                ->limit(6 - $people->count())
                ->get();

            $people = $people->merge($more);
        }

        return $people->map(fn ($person) => [
            'user' => $person,
            'caption' => $person->profile?->bio ?: __('New here.'),
        ]);
    }

    /**
     * Mirrors the dashboard widget's own "no communities yet" fallback —
     * the most-populated public communities, since a new user has no
     * heritage/interest signal on the community side to personalize by.
     */
    #[Computed]
    public function suggestedCommunities()
    {
        return Community::query()
            ->where('visibility', Community::VISIBILITY_PUBLIC)
            ->withCount('activeMembers')
            ->get()
            ->sortByDesc('active_members_count')
            ->filter(fn ($community) => $community->active_members_count > 0)
            ->take(6)
            ->values();
    }

    public function saveRoots(): void
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

        $this->step = 2;
    }

    public function skipRoots(): void
    {
        $this->step = 2;
    }

    public function goToCommunities(): void
    {
        unset($this->suggestedPeople);
        $this->step = 3;
    }

    public function finish(): void
    {
        Auth::user()->completeOnboarding();

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl">
    <div class="mb-6 flex items-center gap-2">
        @for ($i = 1; $i <= 3; $i++)
            <div class="h-1.5 flex-1 rounded-full {{ $i <= $step ? 'bg-cyan-600' : 'bg-stone-200 dark:bg-stone-800' }}"></div>
        @endfor
    </div>

    @if ($step === 1)
        <flux:heading size="xl">{{ __('Welcome to valueAFRIK') }}</flux:heading>
        <flux:subheading>{{ __('A few words about you — languages, heritage, and what you\'re curious about. This is what helps us connect you with the right people.') }}</flux:subheading>

        <form wire:submit="saveRoots" class="mt-6 space-y-6">
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

            <div>
                <flux:label>{{ __('Languages you speak') }}</flux:label>
                <div class="mt-2 max-h-40 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="languageIds" variant="pills" class="flex-wrap">
                        @foreach ($this->languages as $language)
                            <flux:checkbox :value="$language->id" :label="$language->name" />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>

            <div>
                <flux:label>{{ __('Heritage') }}</flux:label>
                <div class="mt-2 max-h-40 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="heritageIds" variant="pills" class="flex-wrap">
                        @foreach ($this->heritages as $heritage)
                            <flux:checkbox :value="$heritage->id" :label="$heritage->name" />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>

            <div>
                <flux:label>{{ __('What are you curious about?') }}</flux:label>
                <div class="mt-2 max-h-40 overflow-y-auto rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:checkbox.group wire:model="interestIds" variant="pills" class="flex-wrap">
                        @foreach ($this->interests as $interest)
                            <flux:checkbox :value="$interest->id" :label="$interest->name" />
                        @endforeach
                    </flux:checkbox.group>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <flux:button type="button" variant="ghost" wire:click="skipRoots">{{ __('Skip for now') }}</flux:button>
                <flux:button type="submit" variant="primary" color="cyan" wire:loading.attr="disabled" wire:target="saveRoots" data-test="onboarding-continue-1">
                    {{ __('Continue') }}
                </flux:button>
            </div>
        </form>
    @elseif ($step === 2)
        <flux:heading size="xl">{{ __('Follow a few people') }}</flux:heading>
        <flux:subheading>{{ __('Your dashboard fills up with what the people you follow share.') }}</flux:subheading>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            @forelse ($this->suggestedPeople as $entry)
                @include('pages.discover._person-card', $entry)
            @empty
                <p class="col-span-2 text-sm text-stone-500 dark:text-stone-400">{{ __('No one else here yet.') }}</p>
            @endforelse
        </div>

        <div class="mt-6 flex items-center justify-between">
            <flux:button variant="ghost" wire:click="$set('step', 1)">{{ __('Back') }}</flux:button>
            <flux:button variant="primary" color="cyan" wire:click="goToCommunities" data-test="onboarding-continue-2">
                {{ __('Continue') }}
            </flux:button>
        </div>
    @else
        <flux:heading size="xl">{{ __('Join a community') }}</flux:heading>
        <flux:subheading>{{ __('Where the conversation is already happening.') }}</flux:subheading>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            @forelse ($this->suggestedCommunities as $community)
                <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800">
                    <a href="{{ route('communities.show', $community) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="size-12 shrink-0 overflow-hidden rounded-xl bg-stone-200 dark:bg-stone-700">
                            @if ($community->avatarUrl())
                                <img src="{{ $community->avatarUrl() }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-stone-500">
                                    <flux:icon.user-group class="size-6" />
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium text-stone-900 dark:text-white">{{ $community->name }}</div>
                            <p class="mt-0.5 text-sm text-stone-500 dark:text-stone-400">
                                {{ trans_choice('1 member|:count members', $community->active_members_count) }}
                            </p>
                        </div>
                    </a>

                    <livewire:pages::communities.join-button :community="$community" :key="'onboarding-join-'.$community->id" />
                </div>
            @empty
                <p class="col-span-2 text-sm text-stone-500 dark:text-stone-400">{{ __('No communities yet — be the first to start one.') }}</p>
            @endforelse
        </div>

        <div class="mt-6 flex items-center justify-between">
            <flux:button variant="ghost" wire:click="$set('step', 2)">{{ __('Back') }}</flux:button>
            <flux:button variant="primary" color="cyan" wire:click="finish" data-test="onboarding-finish">
                {{ __("I'm done — take me to my dashboard") }}
            </flux:button>
        </div>
    @endif
</div>
