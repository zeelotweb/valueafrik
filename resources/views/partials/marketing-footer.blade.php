<footer id="site-footer" class="mt-4 border-t border-stone-200 bg-stone-100 dark:border-stone-800 dark:bg-black">
    <div class="mx-auto max-w-6xl px-6 py-16">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-brand-mark />
                <p class="mt-4 max-w-xs text-sm text-stone-500 dark:text-stone-400">
                    {{ __('Building Bridges Across Cultures — a social platform where identity comes first and curiosity is the reason to connect.') }}
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">{{ __('Platform') }}</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('discover.index') }}" class="hover:text-stone-900 dark:hover:text-white">{{ __('Discover') }}</a></li>
                    <li><a href="{{ route('communities.index') }}" class="hover:text-stone-900 dark:hover:text-white">{{ __('Communities') }}</a></li>
                    <li><a href="{{ route('live.index') }}" class="hover:text-stone-900 dark:hover:text-white">{{ __('Live') }}</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">{{ __('Company') }}</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('guide') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>{{ __('Guide') }}</a></li>
                    <li><a href="{{ route('roadmap') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>{{ __('Roadmap') }}</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">{{ __('Legal') }}</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>{{ __('Privacy Policy') }}</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>{{ __('Terms of Service') }}</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-stone-200 pt-8 text-sm text-stone-500 sm:flex-row dark:border-stone-800 dark:text-stone-500">
            <span>&copy; {{ now()->year }} valueAFRIK. All rights reserved.</span>

            <div class="flex items-center gap-4 text-stone-300 dark:text-stone-700">
                <span class="text-stone-500 dark:text-stone-400"><x-language-switcher variant="footer" anchor="site-footer" /></span>
                <span title="{{ __('X — coming soon') }}">X</span>
                <span title="{{ __('Instagram — coming soon') }}">{{ __('Instagram') }}</span>
                <span title="{{ __('LinkedIn — coming soon') }}">{{ __('LinkedIn') }}</span>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-stone-400 dark:text-stone-600">
            {{ __('🚧 Beta — this platform is under active development. Expect changes as we build together.') }}
        </p>
    </div>
</footer>
