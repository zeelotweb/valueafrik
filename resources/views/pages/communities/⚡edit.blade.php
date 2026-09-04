<?php

use App\Models\Community;
use App\Notifications\CommunityJoinApproved;
use App\Support\SafeNotifier;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit community')] class extends Component {
    public Community $community;

    public string $name = '';
    public string $description = '';
    public string $visibility = '';
    public string $participation_level = '';

    public function mount(Community $community): void
    {
        abort_unless(Auth::id() === $community->owner_id, 403);

        $this->community = $community;
        $this->name = $community->name;
        $this->description = $community->description ?? '';
        $this->visibility = $community->visibility;
        $this->participation_level = $community->participation_level;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'description' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', 'in:public,private,followers_only'],
            'participation_level' => ['required', 'in:post,view_only'],
        ]);

        $wasPrivate = $this->community->visibility === Community::VISIBILITY_PRIVATE;

        $this->community->update($validated);

        // Pending requests were only pending because of the private gate —
        // once that gate lifts, there's nothing left to approve them against.
        if ($wasPrivate && $validated['visibility'] !== Community::VISIBILITY_PRIVATE) {
            $this->community->members()->wherePivot('status', 'pending')->get()->each(function ($user) {
                $this->community->members()->updateExistingPivot($user->id, ['status' => 'active']);
                $user->awardBridgeScore('community_joined', $this->community);
                SafeNotifier::send($user, new CommunityJoinApproved($this->community));
            });
        }

        Flux::toast(variant: 'success', text: __('Community updated.'));

        $this->redirect(route('communities.show', $this->community->fresh()), navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-xl">
    <flux:heading size="xl">{{ __('Edit community') }}</flux:heading>

    <div
        x-data="{
            coverUrl: @js($community->coverUrl()),
            coverUploading: false,
            coverProgress: 0,
            coverError: null,
            coverUploader: null,
            avatarUrl: @js($community->avatarUrl()),
            avatarUploading: false,
            avatarProgress: 0,
            avatarError: null,
            avatarUploader: null,
        }"
        x-init="
            coverUploader = window.createPhotoUploader({ endpoint: @js(route('communities.cover', $community)), maxFileSize: 8 * 1024 * 1024 });
            coverUploader.on('upload-progress', (file, p) => coverProgress = Math.round((p.bytesUploaded / p.bytesTotal) * 100));
            coverUploader.on('upload-success', (file, res) => { coverUrl = res.body.url; coverUploading = false; });
            coverUploader.on('upload-error', (file, err) => { coverError = err.message; coverUploading = false; });

            avatarUploader = window.createPhotoUploader({ endpoint: @js(route('communities.avatar', $community)), maxFileSize: 5 * 1024 * 1024 });
            avatarUploader.on('upload-progress', (file, p) => avatarProgress = Math.round((p.bytesUploaded / p.bytesTotal) * 100));
            avatarUploader.on('upload-success', (file, res) => { avatarUrl = res.body.url; avatarUploading = false; });
            avatarUploader.on('upload-error', (file, err) => { avatarError = err.message; avatarUploading = false; });
        "
        class="mt-6 mb-10"
    >
        <div class="relative">
            <div
                class="h-40 w-full rounded-lg border border-stone-200 bg-stone-100 bg-cover bg-center dark:border-stone-800 dark:bg-stone-800"
                x-bind:style="coverUrl ? `background-image: url('${coverUrl}')` : ''"
            >
                <button
                    type="button"
                    x-on:click="$refs.coverInput.click()"
                    class="absolute top-3 end-3 rounded-md bg-black/60 px-3 py-1.5 text-xs font-medium text-white hover:bg-black/80"
                >
                    {{ __('Change cover') }}
                </button>

                <input
                    type="file"
                    accept="image/*"
                    x-ref="coverInput"
                    class="hidden"
                    x-on:change="
                        coverError = null;
                        if ($event.target.files[0]) {
                            coverUploading = true; coverProgress = 0;
                            try { coverUploader.upload($event.target.files[0]); }
                            catch (e) { coverError = e.message; coverUploading = false; }
                        }
                        $event.target.value = '';
                    "
                />

                <div x-show="coverUploading" style="display: none;" class="absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-lg bg-black/50">
                    <flux:icon.loading variant="mini" class="size-5 text-white" />
                    <div class="h-1.5 w-32 overflow-hidden rounded-full bg-white/25">
                        <div class="h-full rounded-full bg-white transition-all" x-bind:style="`width: ${coverProgress}%`"></div>
                    </div>
                    <span class="text-xs font-medium text-white" x-text="coverProgress + '%'"></span>
                </div>
            </div>

            <div class="absolute -bottom-8 start-4">
                <button
                    type="button"
                    x-on:click="$refs.avatarInput.click()"
                    class="group relative block size-20 overflow-hidden rounded-2xl border-4 border-white bg-stone-200 dark:border-stone-950 dark:bg-stone-700"
                >
                    <img x-show="avatarUrl" x-bind:src="avatarUrl" class="size-full object-cover" alt="">
                    <div x-show="!avatarUrl" class="flex size-full items-center justify-center text-stone-500">
                        <flux:icon.user-group class="size-8" />
                    </div>
                    <div class="absolute inset-0 flex items-center justify-center bg-black/0 text-transparent transition group-hover:bg-black/40 group-hover:text-white">
                        <flux:icon.camera class="size-5" />
                    </div>
                    <div x-show="avatarUploading" style="display: none;" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60">
                        <flux:icon.loading variant="micro" class="size-4 text-white" />
                        <span class="text-[10px] font-medium text-white" x-text="avatarProgress + '%'"></span>
                    </div>
                </button>

                <input
                    type="file"
                    accept="image/*"
                    x-ref="avatarInput"
                    class="hidden"
                    x-on:change="
                        avatarError = null;
                        if ($event.target.files[0]) {
                            avatarUploading = true; avatarProgress = 0;
                            try { avatarUploader.upload($event.target.files[0]); }
                            catch (e) { avatarError = e.message; avatarUploading = false; }
                        }
                        $event.target.value = '';
                    "
                />
            </div>
        </div>

        <div class="mt-10 space-y-1">
            <template x-if="coverError"><p class="text-sm text-red-600" x-text="coverError"></p></template>
            <template x-if="avatarError"><p class="text-sm text-red-600" x-text="avatarError"></p></template>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Name')" />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <flux:radio.group wire:model="visibility" :label="__('Visibility')">
            <flux:radio value="public" :label="__('Public')" description="{{ __('Anyone can find and join.') }}" />
            <flux:radio value="followers_only" :label="__('Followers only')" description="{{ __('Only people who follow you can join.') }}" />
            <flux:radio value="private" :label="__('Private')" description="{{ __('Hidden from listings; join requests need your approval.') }}" />
        </flux:radio.group>

        <flux:radio.group wire:model="participation_level" :label="__('Participation')">
            <flux:radio value="post" :label="__('Anyone can post')" description="{{ __('All members can share posts.') }}" />
            <flux:radio value="view_only" :label="__('View only')" description="{{ __('Only you and monitors can post; members can view.') }}" />
        </flux:radio.group>

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" color="green" wire:loading.attr="disabled">
                {{ __('Save') }}
            </flux:button>

            <flux:button :href="route('communities.show', $community)" wire:navigate variant="ghost">
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>
</div>
