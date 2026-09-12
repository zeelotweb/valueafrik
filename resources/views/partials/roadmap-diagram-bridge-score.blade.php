@php
    $points = config('bridge_score.points');
    $badges = config('bridge_score.badges');
@endphp
<div class="flex flex-col items-center gap-6">
    <div class="flex flex-wrap items-center justify-center gap-2">
        <span class="rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[11px] text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
            Reaction · +{{ $points['reaction_given'] }}
        </span>
        <span class="rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[11px] text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
            Join a circle · +{{ $points['community_joined'] }}
        </span>
        <span class="rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[11px] text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
            Complete a Bridge Post · +{{ $points['bridge_post_completed'] }}
        </span>
        <span class="rounded-full border border-stone-200 bg-white px-2.5 py-1 text-[11px] text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
            Cross-heritage follow · +{{ $points['follow'] + $points['follow_cross_heritage_bonus'] }}
        </span>
    </div>

    <flux:icon.arrow-right class="size-5 rotate-90 text-stone-300 dark:text-stone-700" />

    <div class="relative w-full max-w-lg pt-2 pb-10">
        <div class="h-2 w-full rounded-full bg-gradient-to-r from-cyan-200 to-cyan-600 dark:from-cyan-950 dark:to-cyan-500"></div>

        @foreach ([['pct' => 12, 'threshold' => 10, 'badge' => 'first_bridge'], ['pct' => 38, 'threshold' => 50, 'badge' => 'bridge_builder'], ['pct' => 66, 'threshold' => 150, 'badge' => 'culture_connector'], ['pct' => 94, 'threshold' => 500, 'badge' => 'bridge_architect']] as $tick)
            <div class="absolute top-0 flex -translate-x-1/2 flex-col items-center" style="left: {{ $tick['pct'] }}%">
                <span class="h-4 w-0.5 bg-stone-400 dark:bg-stone-600"></span>
                <span class="mt-1 text-center text-[10px] leading-tight font-medium whitespace-nowrap text-stone-600 dark:text-stone-400">
                    {{ $badges[$tick['threshold']]['name'] }}
                </span>
                <span class="text-[10px] text-stone-400 dark:text-stone-600">{{ $tick['threshold'] }}+</span>
            </div>
        @endforeach
    </div>

    <p class="text-center text-[11px] text-stone-400 dark:text-stone-600">
        Points only ever add up — your badge is whichever threshold your running total has crossed.
    </p>
</div>
