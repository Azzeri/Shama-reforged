@props(['title', 'backHref' => null, 'backLabel' => null])

{{-- Sub-page header: "‹ Parent" link, title below (one line), optional action pill. --}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <div class="flex items-center justify-between gap-3">
        <x-ui.back-button :href="$backHref" :label="$backLabel" />
        @isset($action)
        <div class="flex items-center gap-2 shrink-0">{{ $action }}</div>
        @endisset
    </div>
    <h1 data-large-title class="font-fraunces text-[26px] lg:text-[32px] font-semibold leading-tight tracking-[-0.01em] text-ink">{{ $title }}</h1>
    {{ $slot }}
</div>
