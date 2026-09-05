<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $code = '';

    public bool $showingQrCode = false;
    public bool $showingRecoveryCodes = false;

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    #[Computed]
    public function twoFactorEnabled(): bool
    {
        return Auth::user()->hasEnabledTwoFactorAuthentication();
    }

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());

        $this->showingQrCode = true;

        unset($this->twoFactorEnabled);
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        $confirm(Auth::user(), $this->code);

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = true;

        unset($this->twoFactorEnabled);

        Flux::toast(variant: 'success', text: __('Two-factor authentication confirmed.'));
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());

        $this->reset('code', 'showingQrCode', 'showingRecoveryCodes');

        unset($this->twoFactorEnabled);

        Flux::toast(variant: 'success', text: __('Two-factor authentication disabled.'));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;

        Flux::toast(variant: 'success', text: __('Recovery codes regenerated.'));
    }

}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Security settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Security')" :subheading="__('Manage your password, two-factor authentication, and passkeys')">
        <flux:heading size="lg">{{ __('Update password') }}</flux:heading>
        <flux:subheading>{{ __('Ensure your account is using a long, random password to stay secure') }}</flux:subheading>

        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <flux:input
                wire:model="current_password"
                :label="__('Current password')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />
            <flux:input
                wire:model="password"
                :label="__('New password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" color="cyan" type="submit" data-test="update-password-button">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

    @if (Features::enabled(Features::twoFactorAuthentication()))
        <flux:separator variant="subtle" class="my-10" />

        <flux:heading size="lg">{{ __('Two-factor authentication') }}</flux:heading>
        <flux:subheading>{{ __('Add an extra layer of security to your account using an authenticator app') }}</flux:subheading>

        <div class="mt-6 space-y-6">
            @if ($this->twoFactorEnabled && ! $showingQrCode)
                <flux:callout variant="success" icon="shield-check" :heading="__('Two-factor authentication is enabled.')" />

                @if ($showingRecoveryCodes)
                    <div class="space-y-3">
                        <flux:text>
                            {{ __('Store these recovery codes in a secure password manager. They can be used to recover access to your account if your two-factor authentication device is lost.') }}
                        </flux:text>
                        <div class="grid gap-1 rounded-lg bg-stone-100 p-4 font-mono text-sm dark:bg-stone-800">
                            @foreach (Auth::user()->recoveryCodes() as $code)
                                <div>{{ $code }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex items-center gap-4">
                    <flux:button wire:click="regenerateRecoveryCodes" variant="ghost" data-test="regenerate-recovery-codes-button">
                        {{ __('Regenerate recovery codes') }}
                    </flux:button>
                    <flux:button wire:click="disableTwoFactorAuthentication" variant="danger" data-test="disable-two-factor-button">
                        {{ __('Disable') }}
                    </flux:button>
                </div>
            @elseif ($showingQrCode)
                <flux:text>
                    {{ __('Scan the QR code below with your authenticator app, then enter the generated code to confirm setup.') }}
                </flux:text>

                <div class="w-fit rounded-lg bg-white p-4">
                    {!! Auth::user()->twoFactorQrCodeSvg() !!}
                </div>

                <form wire:submit="confirmTwoFactorAuthentication" class="flex max-w-xs items-end gap-4">
                    <flux:input
                        wire:model="code"
                        :label="__('Confirmation code')"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autofocus
                        required
                        data-test="two-factor-code-input"
                    />
                    <flux:button variant="primary" color="cyan" type="submit" data-test="confirm-two-factor-button">
                        {{ __('Confirm') }}
                    </flux:button>
                </form>
            @else
                <flux:text>
                    {{ __('Two-factor authentication is not enabled yet. Enable it to require a code from your authenticator app when logging in.') }}
                </flux:text>

                <flux:button wire:click="enableTwoFactorAuthentication" variant="primary" color="cyan" data-test="enable-two-factor-button">
                    {{ __('Enable two-factor authentication') }}
                </flux:button>
            @endif
        </div>
    @endif

    @if (Features::enabled(Features::passkeys()))
        <flux:separator variant="subtle" class="my-10" />

        <flux:heading size="lg">{{ __('Passkeys') }}</flux:heading>
        <flux:subheading>{{ __('Manage your passkeys for passwordless sign-in') }}</flux:subheading>

        <div
            x-data="{ name: '', busy: false, error: null }"
            class="mt-6 space-y-6"
        >
                @forelse (Auth::user()->passkeys as $passkey)
                    <div class="flex items-center justify-between rounded-lg bg-white border border-stone-200 px-4 py-3 dark:bg-stone-900 dark:border-stone-800">
                        <div>
                            <flux:text class="font-medium text-stone-900 dark:text-white">{{ $passkey->name }}</flux:text>
                            <flux:text class="text-sm">
                                {{ __('Added :date', ['date' => $passkey->created_at->diffForHumans()]) }}
                                @if ($passkey->last_used_at)
                                    &middot; {{ __('Last used :date', ['date' => $passkey->last_used_at->diffForHumans()]) }}
                                @endif
                            </flux:text>
                        </div>
                        <flux:button
                            variant="danger"
                            size="sm"
                            x-on:click="if (confirm('{{ __('Remove this passkey?') }}')) { window.Passkeys.deletePasskey({{ $passkey->id }}).then(() => window.location.reload()); }"
                        >
                            {{ __('Remove') }}
                        </flux:button>
                    </div>
                @empty
                    <flux:text>
                        {{ __('Add a passkey to sign in without a password') }}
                    </flux:text>
                @endforelse

                <template x-if="error">
                    <flux:callout variant="danger" icon="exclamation-triangle" x-bind:heading="error" />
                </template>

                <form
                    class="flex max-w-sm items-end gap-4"
                    x-on:submit.prevent="
                        busy = true; error = null;
                        window.Passkeys.registerPasskey(name)
                            .then(() => window.location.reload())
                            .catch((e) => { error = e.message; busy = false; });
                    "
                >
                    <flux:input
                        x-model="name"
                        :label="__('Passkey name')"
                        type="text"
                        required
                        placeholder="{{ __('e.g. MacBook Touch ID') }}"
                    />
                    <flux:button type="submit" variant="primary" color="cyan" x-bind:disabled="busy" data-test="add-passkey-button">
                        {{ __('Add passkey') }}
                    </flux:button>
                </form>
            </div>
    @endif
    </x-pages::settings.layout>
</section>
