<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

        @include('partials.marketing-header')

        <main>
            <section class="mx-auto max-w-3xl px-6 py-16">
                <p class="text-sm font-medium tracking-widest text-cyan-600 uppercase dark:text-cyan-400">Legal</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Terms of Service</h1>

                <div class="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-sm text-stone-600 dark:border-stone-700 dark:text-stone-400">
                    This page is a placeholder. valueAFRIK is still under active development, and our full
                    Terms of Service will be published here before the platform is open to the public.
                </div>
            </section>
        </main>

        @include('partials.marketing-footer')

        @fluxScripts
    </body>
</html>
