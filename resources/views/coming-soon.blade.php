<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

        @include('partials.marketing-header')

        <main>
            <section class="mx-auto max-w-xl px-6 py-24 text-center">
                <x-coming-soon-badge />
                <h1 class="mt-4 text-3xl font-bold tracking-tight">{{ __('This is coming soon') }}</h1>
                <p class="mt-3 text-stone-600 dark:text-stone-400">{{ __("We're still building this part of valueAFRIK. Check back soon.") }}</p>
                <a href="{{ route('home') }}" wire:navigate class="btn-flat-primary mt-8 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium">{{ __('Back to home') }}</a>
            </section>
        </main>

        @include('partials.marketing-footer')
        @fluxScripts
    </body>
</html>
