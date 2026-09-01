<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}

         @include('partials.marketing-footer')
    </flux:main>
</x-layouts::app.sidebar>
