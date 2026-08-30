<x-layouts::auth :title="__('Two-factor authentication')">
    <div
        x-data="{ usingRecoveryCode: false }"
        class="flex flex-col gap-6"
    >
        <x-auth-header
            :title="__('Two-factor authentication')"
            :description="__('Enter the code from your authenticator app to continue.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <template x-if="! usingRecoveryCode">
                <flux:input
                    icon="device-phone-mobile"
                    name="code"
                    :label="__('Code')"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    placeholder="123456"
                />
            </template>

            <template x-if="usingRecoveryCode">
                <flux:input
                    icon="key"
                    name="recovery_code"
                    :label="__('Recovery code')"
                    type="text"
                    autocomplete="one-time-code"
                    placeholder="{{ __('Enter a recovery code') }}"
                />
            </template>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="two-factor-login-button">
                    {{ __('Continue') }}
                </flux:button>
            </div>
        </form>

        <flux:link
            href="#"
            x-on:click.prevent="usingRecoveryCode = ! usingRecoveryCode"
            class="text-center text-sm"
        >
            <span x-show="! usingRecoveryCode">{{ __('Use a recovery code instead') }}</span>
            <span x-show="usingRecoveryCode">{{ __('Use an authentication code instead') }}</span>
        </flux:link>
    </div>
</x-layouts::auth>
