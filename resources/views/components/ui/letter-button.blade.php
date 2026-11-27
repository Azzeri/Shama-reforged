{{-- Phone "A–Z" chip in a filter row; opens <x-ui.letter-sheet>. --}}
<button type="button" x-on:click="$dispatch('open-letter-sheet')" aria-label="{{ __('Jump to letter') }}"
    {{ $attributes->merge(['class' => 'lg:hidden inline-flex h-9 shrink-0 items-center rounded-full border border-field bg-surface px-3 text-[13px] font-semibold text-ink whitespace-nowrap']) }}>A–Z</button>
