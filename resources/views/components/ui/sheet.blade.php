@props(['title', 'close' => null, 'xClose' => null, 'label' => null, 'wide' => false, 'fullscreen' => false])

{{--
    Bottom sheet (a centred 440 px dialog on desktop — 880 px with $wide —, Esc closes it): dimmed backdrop, 24px top radius, handle, title + ✕,
    scrollable body, optional footer slot. Show it with @if (Livewire) or
    x-show (Alpine) around the component. $close = Livewire method to call,
    $xClose = Alpine expression. $fullscreen = on the phone a full screen
    instead of a sheet (long lists, a whole recipe); the desktop keeps the dialog.
--}}
@php
$closeAttr = $close ? 'wire:click="' . e($close) . '"' : ($xClose ? 'x-on:click="' . e($xClose) . '"' : '');
$escAttr = $close ? 'x-on:keydown.escape.window="$wire.' . e($close) . '()"' : ($xClose ? 'x-on:keydown.escape.window="' . e($xClose) . '"' : '');
@endphp
<div
    x-data="{
        init() { document.documentElement.classList.add('overflow-hidden') },
        destroy() { document.documentElement.classList.remove('overflow-hidden') },
    }"
    {!! $escAttr !!}
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 flex flex-col justify-end lg:justify-center lg:items-center lg:p-6 overscroll-contain']) }}>
    <button type="button" {!! $closeAttr !!} aria-label="{{ __('Close') }}" class="absolute inset-0 bg-[rgba(31,27,22,0.42)] cursor-default"></button>
    <div role="dialog" aria-modal="true" aria-label="{{ $label ?? $title }}" data-sheet-panel class="relative w-full max-w-xl lg:max-w-none mx-auto bg-surface flex flex-col pb-[env(safe-area-inset-bottom)] {{ $fullscreen ? 'h-dvh max-h-dvh rounded-none pt-[calc(0.5rem+env(safe-area-inset-top))] lg:h-auto' : 'rounded-t-[24px] max-h-[90dvh] pt-2' }} {{ $wide ? 'lg:w-[min(880px,calc(100vw-3rem))]' : 'lg:w-[440px]' }} lg:rounded-[24px] lg:pt-4 lg:pb-0 lg:max-h-[85dvh] lg:shadow-[0_24px_60px_rgba(31,27,22,0.25)]">
        @unless ($fullscreen)
        <div class="w-9 h-1 rounded bg-field self-center shrink-0 lg:hidden"></div>
        @endunless
        <div class="flex items-center justify-between gap-2 px-4 shrink-0">
            <h2 class="font-fraunces text-[20px] font-semibold text-ink truncate">{{ $title }}</h2>
            <button type="button" {!! $closeAttr !!} data-sheet-close aria-label="{{ __('Close') }}" class="-mr-2.5 flex size-11 items-center justify-center text-ink shrink-0">
                <x-icon.x class="size-[18px]" />
            </button>
        </div>
        <div data-sheet-scroll class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 pb-4 pt-1">
            {{ $slot }}
        </div>
        @isset($footer)
        <div class="shrink-0 border-t border-divider px-4 pt-3 pb-4">
            {{ $footer }}
        </div>
        @endisset
    </div>
</div>
