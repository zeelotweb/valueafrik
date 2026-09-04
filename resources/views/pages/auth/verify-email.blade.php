<x-layouts::auth :title="__('Verify email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Verify your email')"
            :description="__('Please verify your email address by clicking on the link we just emailed to you.')"
        />

        @if (session('status') == 'verification-link-sent')
            <flux:callout variant="success" icon="check-circle" :heading="__('A new verification link has been sent to your email address.')" />
        @endif

        <div class="flex flex-col items-center justify-between gap-4">
            <form method="POST" action="{{ route('verification.send') }}" class="w-full">
                @csrf
                <flux:button variant="primary" color="green" type="submit" class="w-full">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:button variant="ghost" type="submit" class="w-full">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
