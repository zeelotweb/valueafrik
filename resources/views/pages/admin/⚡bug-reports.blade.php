<?php

use App\Models\BugReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin — Bug Reports')] class extends Component {
    use WithPagination;

    public string $filter = 'open';

    /**
     * The route middleware already gates the page's initial load — this is
     * the same check applied directly to the component too, since a
     * Livewire component is also independently reachable through its own
     * update endpoint, not only through the route that first rendered it.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function resolve(int $bugReportId): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        BugReport::whereKey($bugReportId)->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        Flux::toast(text: __('Marked resolved.'));
    }

    public function with(): array
    {
        $bugReports = BugReport::query()
            ->with(['user.profile', 'resolver'])
            ->when($this->filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->filter === 'resolved', fn ($q) => $q->where('status', 'resolved'))
            ->latest()
            ->paginate(15);

        return [
            'bugReports' => $bugReports,
            'openCount' => BugReport::where('status', 'open')->count(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Bug Reports') }}</flux:heading>
    <flux:subheading>{{ __('Sent in from the floating "Report a bug" button, wherever testers hit something.') }}</flux:subheading>

    <div class="mt-4 flex items-center gap-2">
        <flux:button size="sm" :variant="$filter === 'open' ? 'primary' : 'ghost'" class="{{ $filter === 'open' ? 'btn-flat-primary' : '' }}" wire:click="setFilter('open')">
            {{ __('Open') }} ({{ $openCount }})
        </flux:button>
        <flux:button size="sm" :variant="$filter === 'resolved' ? 'primary' : 'ghost'" class="{{ $filter === 'resolved' ? 'btn-flat-primary' : '' }}" wire:click="setFilter('resolved')">
            {{ __('Resolved') }}
        </flux:button>
        <flux:button size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" class="{{ $filter === 'all' ? 'btn-flat-primary' : '' }}" wire:click="setFilter('all')">
            {{ __('All') }}
        </flux:button>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($bugReports as $bugReport)
            <div class="surface-card p-4" wire:key="bug-report-{{ $bugReport->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <span class="font-medium text-stone-900 dark:text-white">{{ $bugReport->user->name }}</span>
                            <flux:badge size="sm" :color="$bugReport->status === 'open' ? 'amber' : 'zinc'">
                                {{ $bugReport->status === 'open' ? __('Open') : __('Resolved') }}
                            </flux:badge>
                        </div>

                        <p class="mt-2 text-sm text-stone-700 dark:text-stone-300">{{ $bugReport->description }}</p>

                        <p class="mt-2 truncate text-xs text-stone-400 dark:text-stone-500">
                            <a href="{{ $bugReport->url }}" target="_blank" rel="noopener" class="hover:underline">{{ $bugReport->url }}</a>
                        </p>

                        <p class="mt-1 text-xs text-stone-400 dark:text-stone-500">
                            {{ $bugReport->created_at->diffForHumans() }}
                            @if ($bugReport->status === 'resolved' && $bugReport->resolver)
                                &middot; {{ __('Resolved by :name', ['name' => $bugReport->resolver->name]) }}
                            @endif
                        </p>
                    </div>

                    @if ($bugReport->status === 'open')
                        <flux:button size="sm" variant="ghost" class="shrink-0" wire:click="resolve({{ $bugReport->id }})">
                            {{ __('Mark resolved') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __('Nothing here.') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{ $bugReports->links() }}
</div>
