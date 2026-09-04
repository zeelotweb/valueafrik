<footer class="border-t border-stone-200 dark:border-stone-800">
    <div class="mx-auto max-w-6xl px-6 py-16">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight" wire:navigate>
                    <img src="{{ asset('apple-touch-icon-180x180.png') }}" alt="valueAFRIK" class="size-8 dark:invert">
                    <span><span class="text-green-600">value</span><span class="text-stone-900 dark:text-white">AFRIK</span></span>
                </a>
                <p class="mt-4 max-w-xs text-sm text-stone-500 dark:text-stone-400">
                    Building Bridges Across Cultures — a social platform where identity comes first and
                    curiosity is the reason to connect.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Platform</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('discover.index') }}" class="hover:text-stone-900 dark:hover:text-white">Discover</a></li>
                    <li><a href="{{ route('communities.index') }}" class="hover:text-stone-900 dark:hover:text-white">Communities</a></li>
                    <li><a href="{{ route('live.index') }}" class="hover:text-stone-900 dark:hover:text-white">Live</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Company</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('guide') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Guide</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-stone-900 dark:text-white">Legal</h3>
                <ul class="mt-4 space-y-2 text-sm text-stone-500 dark:text-stone-400">
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Privacy Policy</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-stone-900 dark:hover:text-white" wire:navigate>Terms of Service</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-stone-200 pt-8 text-sm text-stone-500 sm:flex-row dark:border-stone-800 dark:text-stone-500">
            <span>&copy; {{ now()->year }} valueAFRIK. All rights reserved.</span>

            <div class="flex items-center gap-4 text-stone-300 dark:text-stone-700">
                <span title="X — coming soon">X</span>
                <span title="Instagram — coming soon">Instagram</span>
                <span title="LinkedIn — coming soon">LinkedIn</span>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-stone-400 dark:text-stone-600">
            🚧 Beta — this platform is under active development. Expect changes as we build together.
        </p>
    </div>
</footer>
