<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function with(): array
    {
        $viewer = Auth::user();
        $excluded = $viewer->following()->pluck('users.id')->push($viewer->id);
        $viewerInterestIds = $viewer->interests()->pluck('interests.id');
        $viewerHeritageIds = $viewer->heritages()->pluck('heritages.id');

        $people = collect();

        if ($viewerInterestIds->isNotEmpty()) {
            $people = User::query()
                ->whereKeyNot($excluded)
                ->whereHas('interests', fn ($query) => $query->whereIn('interests.id', $viewerInterestIds))
                ->withCount(['interests as shared_interests_count' => fn ($query) => $query->whereIn('interests.id', $viewerInterestIds)])
                ->with(['profile', 'heritages'])
                ->orderByDesc('shared_interests_count')
                ->limit(3)
                ->get()
                ->map(fn ($person) => [
                    'user' => $person,
                    'caption' => trans_choice('1 shared interest|:count shared interests', $person->shared_interests_count),
                ]);
        }

        if ($people->count() < 3 && $viewerHeritageIds->isNotEmpty()) {
            $people = $people->concat(
                User::query()
                    ->whereKeyNot($excluded->concat($people->pluck('user.id')))
                    ->whereHas('heritages')
                    ->whereDoesntHave('heritages', fn ($query) => $query->whereIn('heritages.id', $viewerHeritageIds))
                    ->with(['profile', 'heritages'])
                    ->inRandomOrder()
                    ->limit(3 - $people->count())
                    ->get()
                    ->map(fn ($person) => [
                        'user' => $person,
                        'caption' => __('A different heritage — a bridge worth building.'),
                    ])
            );
        }

        if ($people->count() < 3) {
            $people = $people->concat(
                User::query()
                    ->whereKeyNot($excluded->concat($people->pluck('user.id')))
                    ->with(['profile', 'heritages'])
                    ->latest()
                    ->limit(3 - $people->count())
                    ->get()
                    ->map(fn ($person) => [
                        'user' => $person,
                        'caption' => __('New here.'),
                    ])
            );
        }

        return ['people' => $people->take(3)];
    }
}; ?>

<div class="space-y-2" wire:key="dashboard-people">
    @forelse ($people as $entry)
        <div class="flex items-center gap-3 rounded-xl bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800" wire:key="dashboard-person-{{ $entry['user']->id }}">
            <a href="{{ route('profile.show', $entry['user']) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3">
                <div class="size-11 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    @if ($entry['user']->profile?->avatarUrl())
                        <img src="{{ $entry['user']->profile->avatarUrl() }}" class="size-full object-cover">
                    @else
                        <div class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-5" />
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-stone-900 dark:text-white">{{ $entry['user']->name }}</div>
                    <p class="mt-0.5 truncate text-sm text-stone-500 dark:text-stone-400">{{ $entry['caption'] }}</p>
                </div>
            </a>

            <livewire:pages::profile.follow-button :user="$entry['user']" :key="'dashboard-follow-'.$entry['user']->id" :icon-only="true" />
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-700">
            <flux:text>{{ __('No one else here yet.') }}</flux:text>
        </div>
    @endforelse
</div>
