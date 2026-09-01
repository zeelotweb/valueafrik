<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Discover')] class extends Component {
    public function with(): array
    {
        $viewer = Auth::user();
        $followingIds = $viewer->following()->pluck('users.id');
        $viewerInterestIds = $viewer->interests()->pluck('interests.id');
        $viewerHeritageIds = $viewer->heritages()->pluck('heritages.id');

        $excluded = $followingIds->push($viewer->id);

        $sharedInterests = collect();

        if ($viewerInterestIds->isNotEmpty()) {
            $sharedInterests = User::query()
                ->whereKeyNot($excluded)
                ->whereHas('interests', fn ($query) => $query->whereIn('interests.id', $viewerInterestIds))
                ->withCount(['interests as shared_interests_count' => fn ($query) => $query->whereIn('interests.id', $viewerInterestIds)])
                ->with(['profile', 'heritages'])
                ->orderByDesc('shared_interests_count')
                ->limit(10)
                ->get()
                ->map(fn ($person) => [
                    'user' => $person,
                    'caption' => trans_choice('1 shared interest|:count shared interests', $person->shared_interests_count),
                ]);
        }

        $crossHeritage = collect();

        if ($viewerHeritageIds->isNotEmpty()) {
            $crossHeritage = User::query()
                ->whereKeyNot($excluded)
                ->whereHas('heritages')
                ->whereDoesntHave('heritages', fn ($query) => $query->whereIn('heritages.id', $viewerHeritageIds))
                ->with(['profile', 'heritages'])
                ->inRandomOrder()
                ->limit(10)
                ->get()
                ->map(fn ($person) => [
                    'user' => $person,
                    'caption' => __('A different heritage — a bridge worth building.'),
                ]);
        }

        $newHere = User::query()
            ->whereKeyNot($excluded)
            ->with(['profile', 'heritages'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($person) => [
                'user' => $person,
                'caption' => __('New here.'),
            ]);

        return [
            'sharedInterests' => $sharedInterests,
            'crossHeritage' => $crossHeritage,
            'newHere' => $newHere,
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Discover') }}</flux:heading>
    <flux:subheading>{{ __('People worth connecting with — through curiosity, not follower counts.') }}</flux:subheading>

    @if ($sharedInterests->isEmpty() && $crossHeritage->isEmpty() && $newHere->isEmpty())
        <div class="mt-6 rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
            <flux:text>{{ __('No one else here yet.') }}</flux:text>
        </div>
    @endif

    @if ($sharedInterests->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="lg">{{ __('Shares your curiosities') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($sharedInterests as $entry)
                    @include('pages.discover._person-card', $entry)
                @endforeach
            </div>
        </div>
    @endif

    @if ($crossHeritage->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="lg">{{ __('A different perspective') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($crossHeritage as $entry)
                    @include('pages.discover._person-card', $entry)
                @endforeach
            </div>
        </div>
    @endif

    @if ($newHere->isNotEmpty())
        <div class="mt-8">
            <flux:heading size="lg">{{ __('New here') }}</flux:heading>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($newHere as $entry)
                    @include('pages.discover._person-card', $entry)
                @endforeach
            </div>
        </div>
    @endif
</div>
