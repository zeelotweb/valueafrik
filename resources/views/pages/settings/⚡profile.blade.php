<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <div
            x-data="{
                coverUrl: @js(Auth::user()->profile?->coverUrl()),
                coverUploading: false,
                coverProgress: 0,
                coverError: null,
                coverUploader: null,
                avatarUrl: @js(Auth::user()->profile?->avatarUrl()),
                avatarUploading: false,
                avatarProgress: 0,
                avatarError: null,
                avatarUploader: null,
            }"
            x-init="
                coverUploader = window.createPhotoUploader({ endpoint: @js(route('profile.cover')), maxFileSize: 8 * 1024 * 1024 });
                coverUploader.on('upload-progress', (file, p) => coverProgress = Math.round((p.bytesUploaded / p.bytesTotal) * 100));
                coverUploader.on('upload-success', (file, res) => { coverUrl = res.body.url; coverUploading = false; });
                coverUploader.on('upload-error', (file, err) => { coverError = err.message; coverUploading = false; });

                avatarUploader = window.createPhotoUploader({ endpoint: @js(route('profile.avatar')), maxFileSize: 5 * 1024 * 1024 });
                avatarUploader.on('upload-progress', (file, p) => avatarProgress = Math.round((p.bytesUploaded / p.bytesTotal) * 100));
                avatarUploader.on('upload-success', (file, res) => { avatarUrl = res.body.url; avatarUploading = false; });
                avatarUploader.on('upload-error', (file, err) => { avatarError = err.message; avatarUploading = false; });
            "
            class="mb-10"
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
                        data-test="change-cover-button"
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

                    <div x-show="coverUploading" class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/40">
                        <span class="text-sm font-medium text-white" x-text="coverProgress + '%'"></span>
                    </div>
                </div>

                <div class="absolute -bottom-8 start-4">
                    <button
                        type="button"
                        x-on:click="$refs.avatarInput.click()"
                        class="group relative block size-20 overflow-hidden rounded-full border-4 border-white bg-stone-200 dark:border-stone-950 dark:bg-stone-700"
                        data-test="change-avatar-button"
                    >
                        <img x-show="avatarUrl" x-bind:src="avatarUrl" class="size-full object-cover" alt="">
                        <div x-show="!avatarUrl" class="flex size-full items-center justify-center text-stone-500">
                            <flux:icon.user class="size-8" />
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center bg-black/0 text-transparent transition group-hover:bg-black/40 group-hover:text-white">
                            <flux:icon.camera class="size-5" />
                        </div>
                        <div x-show="avatarUploading" class="absolute inset-0 flex items-center justify-center bg-black/50">
                            <span class="text-xs font-medium text-white" x-text="avatarProgress + '%'"></span>
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

        <form wire:submit="updateProfileInformation" class="w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" color="cyan" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

            <livewire:pages::settings.delete-user-form />
    </x-pages::settings.layout>
</section>
