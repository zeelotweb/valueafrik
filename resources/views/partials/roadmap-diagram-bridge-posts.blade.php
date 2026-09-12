<div class="flex flex-col items-center gap-4">
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-6">
        <div class="flex flex-col items-center gap-2">
            <span class="flex size-9 items-center justify-center rounded-full bg-stone-200 dark:bg-stone-700">
                <flux:icon.user class="size-4 text-stone-500" />
            </span>
            <div class="flex w-40 flex-col items-center gap-1 rounded-lg border border-dashed border-stone-300 bg-white p-3 text-center dark:border-stone-700 dark:bg-stone-900">
                <flux:icon.lock-closed class="size-4 text-stone-400 dark:text-stone-600" />
                <span class="text-xs text-stone-500 dark:text-stone-400">Their side, in progress</span>
            </div>
        </div>

        <span class="text-xs font-medium text-stone-400 dark:text-stone-600">+</span>

        <div class="flex flex-col items-center gap-2">
            <span class="flex size-9 items-center justify-center rounded-full bg-stone-200 dark:bg-stone-700">
                <flux:icon.user class="size-4 text-stone-500" />
            </span>
            <div class="flex w-40 flex-col items-center gap-1 rounded-lg border border-dashed border-stone-300 bg-white p-3 text-center dark:border-stone-700 dark:bg-stone-900">
                <flux:icon.lock-closed class="size-4 text-stone-400 dark:text-stone-600" />
                <span class="text-xs text-stone-500 dark:text-stone-400">Your side, in progress</span>
            </div>
        </div>
    </div>

    <flux:icon.arrow-right class="size-6 rotate-90 text-stone-300 dark:text-stone-700" />

    <div class="w-full max-w-sm rounded-xl border border-cyan-200 dark:border-cyan-900">
        <div class="flex items-center gap-2 rounded-t-xl bg-cyan-50 px-3 py-1.5 dark:bg-cyan-950/40">
            <flux:icon.chat-bubble-left-right class="size-4 text-cyan-600 dark:text-cyan-400" />
            <span class="text-xs font-medium text-cyan-700 dark:text-cyan-300">Bridge Post — live</span>
        </div>
        <div class="grid grid-cols-2 divide-x divide-stone-200 dark:divide-stone-800">
            <div class="p-3 text-xs text-stone-600 dark:text-stone-400">Their side, revealed</div>
            <div class="p-3 text-xs text-stone-600 dark:text-stone-400">Your side, revealed</div>
        </div>
    </div>
</div>
