@props(['letters', 'prefix'])

{{--
    Phone "A–Z": a bottom sheet with a grid of the letters that have items;
    a letter scrolls the page to its header. Opened by an "A–Z" button that
    dispatches open-letter-sheet (see <x-ui.letter-button>).
--}}
<div x-data="{ open: false }" x-on:open-letter-sheet.window="open = true" x-on:keydown.escape.window="open = false" class="lg:hidden">
    <template x-teleport="body">
        <div x-show="open" x-transition.opacity style="display: none" class="fixed inset-0 z-50 flex flex-col justify-end">
            <button type="button" x-on:click="open = false" aria-label="{{ __('Close') }}" class="absolute inset-0 bg-[rgba(31,27,22,0.42)] cursor-default"></button>
            <div role="dialog" aria-modal="true" aria-label="{{ __('Jump to letter') }}" data-sheet-panel
                class="relative flex flex-col gap-3.5 rounded-t-[24px] bg-surface px-4 pt-2 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
                <div class="h-1 w-9 self-center rounded bg-field"></div>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ __('Jump to letter') }}</h2>
                    <button type="button" x-on:click="open = false" data-sheet-close aria-label="{{ __('Close') }}" class="-mr-2.5 flex size-11 items-center justify-center text-ink"><x-icon.x class="size-[18px]" /></button>
                </div>
                <div class="grid grid-cols-6 gap-2">
                    @foreach ($letters as $letter)
                    <button type="button" wire:key="{{ $prefix }}-sheet-{{ $letter }}"
                        x-on:click="open = false; $nextTick(() => window.jumpToLetter('{{ \App\Support\Alphabet::anchor($prefix, $letter) }}'))"
                        aria-label="{{ __('Jump to letter :letter', ['letter' => $letter]) }}"
                        class="h-12 rounded-xl border border-field bg-surface text-[17px] font-semibold text-ink">{{ $letter }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </template>
</div>
