@php
    $wireModel ??= 'body';
    $placeholder ??= '';
    $rows ??= 4;
    $class ??= '';
@endphp
<div
    x-data="{
        query: '',
        results: [],
        open: false,
        mentionStart: null,

        onInput(e) {
            const el = e.target;
            const cursor = el.selectionStart;
            const before = el.value.slice(0, cursor);
            const match = before.match(/(?:^|\s)@([a-zA-Z0-9_-]{0,50})$/);

            if (! match || match[1].length < 1) {
                this.open = false;
                return;
            }

            this.query = match[1];
            this.mentionStart = cursor - match[1].length - 1;

            fetch('{{ route('mentions.search') }}?q=' + encodeURIComponent(this.query))
                .then(r => r.json())
                .then(data => {
                    this.results = data;
                    this.open = data.length > 0;
                });
        },

        select(username, el) {
            const cursor = el.selectionStart;
            const before = el.value.slice(0, this.mentionStart);
            const after = el.value.slice(cursor);
            const inserted = '@' + username + ' ';

            el.value = before + inserted + after;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            this.open = false;

            this.$nextTick(() => {
                const pos = before.length + inserted.length;
                el.setSelectionRange(pos, pos);
                el.focus();
            });
        },
    }"
    class="relative {{ $class }}"
>
    <flux:textarea
        {{ $attributes }}
        wire:model="{{ $wireModel }}"
        x-ref="mentionTextarea"
        x-on:input="onInput($event)"
        x-on:keydown.escape="open = false"
        placeholder="{{ $placeholder }}"
        rows="{{ $rows }}"
    />

    <div
        x-show="open"
        x-on:click.outside="open = false"
        style="display: none;"
        class="absolute z-20 mt-1 w-full max-w-xs overflow-hidden rounded-lg bg-white border border-stone-200 shadow-lg dark:bg-stone-900 dark:border-stone-800"
    >
        <template x-for="result in results" :key="result.username">
            <button
                type="button"
                x-on:click="select(result.username, $refs.mentionTextarea)"
                class="flex w-full items-center gap-2 px-3 py-2 text-start hover:bg-stone-100 dark:hover:bg-stone-800"
            >
                <span class="size-6 shrink-0 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700">
                    <img x-show="result.avatarUrl" x-bind:src="result.avatarUrl" class="size-full object-cover">
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-stone-900 dark:text-white" x-text="result.name"></span>
                    <span class="block truncate text-xs text-stone-400" x-text="'@' + result.username"></span>
                </span>
            </button>
        </template>
    </div>
</div>
