<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifications')] class extends Component {
    public string $vapidPublicKey = '';

    public function mount(): void
    {
        $this->vapidPublicKey = (string) config('webpush.vapid.public_key');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Notifications') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Get notified on this device, even when valueAFRIK isn\'t open')">
        <div
            x-data="{
                vapidPublicKey: @js($vapidPublicKey),
                storeUrl: @js(route('push-subscriptions.store')),
                destroyUrl: @js(route('push-subscriptions.destroy')),
                csrfToken: @js(csrf_token()),
                supported: false,
                permission: 'default',
                subscribed: false,
                loading: false,
                init() {
                    this.supported = 'serviceWorker' in navigator && 'PushManager' in window;
                    this.permission = this.supported ? Notification.permission : 'denied';

                    if (! this.supported) {
                        return;
                    }

                    navigator.serviceWorker.getRegistration().then((registration) => {
                        if (! registration) {
                            this.subscribed = false;
                            return;
                        }

                        registration.pushManager.getSubscription().then((subscription) => {
                            this.subscribed = subscription !== null;
                        });
                    });
                },
                urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);

                    for (let i = 0; i < rawData.length; i++) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }

                    return outputArray;
                },
                async enable() {
                    this.loading = true;

                    try {
                        this.permission = await Notification.requestPermission();

                        if (this.permission !== 'granted') {
                            return;
                        }

                        const registration = await navigator.serviceWorker.register('/sw.js');
                        await navigator.serviceWorker.ready;

                        const subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey),
                        });

                        await fetch(this.storeUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                            body: JSON.stringify(subscription.toJSON()),
                        });

                        this.subscribed = true;
                    } finally {
                        this.loading = false;
                    }
                },
                async disable() {
                    this.loading = true;

                    try {
                        const registration = await navigator.serviceWorker.getRegistration();
                        const subscription = registration ? await registration.pushManager.getSubscription() : null;

                        if (subscription) {
                            await fetch(this.destroyUrl, {
                                method: 'DELETE',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                                body: JSON.stringify({ endpoint: subscription.endpoint }),
                            });

                            await subscription.unsubscribe();
                        }

                        this.subscribed = false;
                    } finally {
                        this.loading = false;
                    }
                },
            }"
            class="rounded-lg bg-white border border-stone-200 p-4 dark:bg-stone-900 dark:border-stone-800"
        >
            <template x-if="!supported">
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    {{ __("This browser doesn't support push notifications.") }}
                </p>
            </template>

            <template x-if="supported && permission === 'denied'">
                <p class="text-sm text-stone-500 dark:text-stone-400">
                    {{ __('Notifications are blocked for this site in your browser settings. Allow them there to turn this on.') }}
                </p>
            </template>

            <template x-if="supported && permission !== 'denied'">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-stone-900 dark:text-white">{{ __('Push notifications') }}</p>
                        <p class="text-sm text-stone-500 dark:text-stone-400" x-text="subscribed ? {{ Js::from(__('Enabled on this device')) }} : {{ Js::from(__('Not enabled on this device')) }}"></p>
                    </div>

                    <flux:button
                        x-on:click="subscribed ? disable() : enable()"
                        x-bind:disabled="loading"
                        variant="primary"
                        x-bind:class="subscribed ? '!bg-stone-600 hover:!bg-stone-500' : ''"
                        data-test="push-toggle-button"
                    >
                        <span x-show="!loading" x-text="subscribed ? {{ Js::from(__('Disable')) }} : {{ Js::from(__('Enable')) }}"></span>
                        <span x-show="loading" style="display: none;">{{ __('Working…') }}</span>
                    </flux:button>
                </div>
            </template>
        </div>
    </x-pages::settings.layout>
</section>
