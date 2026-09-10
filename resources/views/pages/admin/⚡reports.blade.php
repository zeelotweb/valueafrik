<?php

use App\Models\CommunityReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin — Reports')] class extends Component {
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

    public function dismiss(int $reportId): void
    {
        $report = CommunityReport::findOrFail($reportId);

        $report->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        Flux::toast(text: __('Report dismissed.'));
    }

    /**
     * Deletes the reported content itself (not just the report) — the
     * platform-wide "remove content" capability community moderation
     * doesn't have, since a community owner can only act inside their own
     * community.
     */
    public function removeContent(int $reportId): void
    {
        $report = CommunityReport::with('reportable')->findOrFail($reportId);

        $report->reportable?->delete();

        $report->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        Flux::toast(variant: 'success', text: __('Content removed and report resolved.'));
    }

    public function with(): array
    {
        $reports = CommunityReport::query()
            ->with(['reporter.profile', 'community', 'reportable.user', 'resolver'])
            ->when($this->filter === 'open', fn ($q) => $q->where('status', 'open'))
            ->when($this->filter === 'resolved', fn ($q) => $q->where('status', 'resolved'))
            ->latest()
            ->paginate(15);

        return [
            'reports' => $reports,
            'openCount' => CommunityReport::where('status', 'open')->count(),
        ];
    }
}; ?>

<div class="mx-auto w-full max-w-3xl">
    <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
    <flux:subheading>{{ __('Content reported across every community on the platform.') }}</flux:subheading>

    <div class="mt-4 flex items-center gap-2">
        <flux:button size="sm" :variant="$filter === 'open' ? 'primary' : 'ghost'" color="cyan" wire:click="setFilter('open')">
            {{ __('Open') }} ({{ $openCount }})
        </flux:button>
        <flux:button size="sm" :variant="$filter === 'resolved' ? 'primary' : 'ghost'" color="cyan" wire:click="setFilter('resolved')">
            {{ __('Resolved') }}
        </flux:button>
        <flux:button size="sm" :variant="$filter === 'all' ? 'primary' : 'ghost'" color="cyan" wire:click="setFilter('all')">
            {{ __('All') }}
        </flux:button>
    </div>

    <div class="mt-6 space-y-3">
        @forelse ($reports as $report)
            <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="report-{{ $report->id }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                            <span class="font-medium text-stone-900 dark:text-white">{{ $report->reporter->name }}</span>
                            <span class="text-stone-400">{{ __('reported a post in') }}</span>
                            @if ($report->community)
                                <a href="{{ route('communities.show', $report->community) }}" wire:navigate class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">{{ $report->community->name }}</a>
                            @endif
                            <flux:badge size="sm" :color="$report->status === 'open' ? 'amber' : 'zinc'">
                                {{ $report->status === 'open' ? __('Open') : __('Resolved') }}
                            </flux:badge>
                        </div>

                        <p class="mt-2 text-sm text-stone-700 dark:text-stone-300">
                            <span class="font-medium">{{ __('Reason:') }}</span> {{ $report->reason }}
                        </p>

                        @if ($report->reportable)
                            <div class="mt-2 rounded-lg bg-stone-100 p-3 text-sm text-stone-600 dark:bg-stone-800 dark:text-stone-400">
                                <span class="font-medium">{{ $report->reportable->user?->name ?? __('Unknown author') }}:</span>
                                {{ Str::limit($report->reportable->body, 200) ?: __('(photo only)') }}
                            </div>
                        @else
                            <p class="mt-2 text-sm italic text-stone-400 dark:text-stone-500">{{ __('The reported content has already been removed.') }}</p>
                        @endif

                        <p class="mt-2 text-xs text-stone-400 dark:text-stone-500">
                            {{ $report->created_at->diffForHumans() }}
                            @if ($report->status === 'resolved' && $report->resolver)
                                &middot; {{ __('Resolved by :name', ['name' => $report->resolver->name]) }}
                            @endif
                        </p>
                    </div>

                    @if ($report->status === 'open')
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="dismiss({{ $report->id }})">
                                {{ __('Dismiss') }}
                            </flux:button>
                            @if ($report->reportable)
                                <flux:button size="sm" variant="danger" wire:click="removeContent({{ $report->id }})" wire:confirm="{{ __('Remove this content? This cannot be undone.') }}">
                                    {{ __('Remove content') }}
                                </flux:button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
                <flux:text>{{ __('Nothing here.') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{ $reports->links() }}
</div>
