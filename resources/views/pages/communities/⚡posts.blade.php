<?php

use App\Models\Community;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Community $community;
    public ?int $reportingPostId = null;
    public string $reportReason = '';

    #[On('community-post-created')]
    #[On('community-membership-changed')]
    public function refresh(): void
    {
        $this->resetPage();
    }

    public function delete(int $postId): void
    {
        $post = CommunityPost::whereKey($postId)->where('community_id', $this->community->id)->firstOrFail();

        abort_unless($post->user_id === Auth::id() || $this->community->canModerate(Auth::user()), 403);

        foreach ($post->media as $media) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $post->delete();
    }

    public function startReport(int $postId): void
    {
        $this->reportingPostId = $postId;
        $this->reportReason = '';
    }

    public function cancelReport(): void
    {
        $this->reportingPostId = null;
        $this->reportReason = '';
    }

    public function submitReport(): void
    {
        $this->validate(['reportReason' => ['required', 'string', 'max:1000']]);

        $post = CommunityPost::whereKey($this->reportingPostId)->where('community_id', $this->community->id)->firstOrFail();

        $report = new CommunityReport([
            'community_id' => $this->community->id,
            'reporter_id' => Auth::id(),
            'reason' => $this->reportReason,
        ]);

        $report->reportable()->associate($post);
        $report->save();

        $this->reportingPostId = null;
        $this->reportReason = '';

        Flux::toast(variant: 'success', text: __('Report submitted to the community and platform admins.'));
    }

    public function with(): array
    {
        return [
            'posts' => $this->community->posts()
                ->with(['user.profile', 'media'])
                ->latest()
                ->paginate(10),
        ];
    }
}; ?>

<div class="space-y-4" wire:key="community-posts-{{ $community->id }}">
    @forelse ($posts as $post)
        @php $isMine = $post->user_id === Auth::id(); @endphp

        <div class="rounded-xl bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800" wire:key="community-post-{{ $post->id }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                        @if ($post->user->profile?->avatarUrl())
                            <img src="{{ $post->user->profile->avatarUrl() }}" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-center justify-center text-stone-500">
                                <flux:icon.user class="size-5" />
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="font-medium text-stone-900 dark:text-white">{{ $post->user->name }}</div>
                        <div class="text-xs text-stone-500 dark:text-stone-400">{{ $post->created_at->diffForHumans() }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    @if (! $isMine)
                        <flux:button size="sm" variant="ghost" wire:click="startReport({{ $post->id }})">
                            <flux:icon.flag class="size-4" />
                        </flux:button>
                    @endif

                    @if ($isMine || $community->canModerate(Auth::user()))
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="delete({{ $post->id }})"
                            wire:confirm="{{ __('Delete this post?') }}"
                        >
                            <flux:icon.trash class="size-4" />
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($post->body)
                <p class="mt-3 whitespace-pre-line text-stone-700 dark:text-stone-300">{{ $post->body }}</p>
            @endif

            @if ($post->media->isNotEmpty())
                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($post->media as $media)
                        <img src="{{ $media->url() }}" class="aspect-square w-full rounded-lg object-cover">
                    @endforeach
                </div>
            @endif

            @if ($reportingPostId === $post->id)
                <div class="mt-3 rounded-lg bg-white border border-stone-200 p-3 dark:bg-stone-900 dark:border-stone-800">
                    <flux:textarea wire:model="reportReason" :label="__('Why are you reporting this?')" rows="2" />
                    <div class="mt-2 flex justify-end gap-2">
                        <flux:button size="sm" variant="ghost" wire:click="cancelReport">{{ __('Cancel') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="submitReport">{{ __('Submit report') }}</flux:button>
                    </div>
                    @error('reportReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-lg border border-dashed border-stone-300 p-6 text-center dark:border-stone-800">
            <flux:text>{{ __('No posts yet.') }}</flux:text>
        </div>
    @endforelse

    {{ $posts->links() }}
</div>
