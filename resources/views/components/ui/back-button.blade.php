@props(['href' => null, 'label' => null])

{{-- "‹ Parent screen" link above a sub-page title. --}}
@if ($href)
<a
    href="{{ $href }}"
    data-back-button
    x-data
    x-on:click.prevent="window.goBack($el.href)"
    {{ $attributes->merge(['class' => '-ml-2 inline-flex h-11 items-center gap-1 px-2 text-[15px] font-medium text-ink shrink-0']) }}>
    <x-icon.chevron-left class="size-5" />
    {{ $label ?? __('Back') }}
</a>
@else
<button
    type="button"
    {{ $attributes->merge(['class' => '-ml-2 inline-flex h-11 items-center gap-1 px-2 text-[15px] font-medium text-ink shrink-0']) }}>
    <x-icon.chevron-left class="size-5" />
    {{ $label ?? __('Back') }}
</button>
@endif
