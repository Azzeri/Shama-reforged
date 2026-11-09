@props(['href' => null])

@if ($href)
<a
    href="{{ $href }}"
    x-data
    x-on:click.prevent="window.goBack($el.href)"
    {{ $attributes->merge(['class' => '-ml-2 inline-flex items-center gap-0.5 px-2 py-1.5 font-manrope text-[17px] text-forest shrink-0']) }}>
    <flux:icon.chevron-left class="size-5 -mr-0.5" variant="solid" />
    {{ __('Back') }}
</a>
@else
<button
    type="button"
    {{ $attributes->merge(['class' => '-ml-2 inline-flex items-center gap-0.5 px-2 py-1.5 font-manrope text-[17px] text-forest shrink-0']) }}>
    <flux:icon.chevron-left class="size-5 -mr-0.5" variant="solid" />
    {{ __('Back') }}
</button>
@endif
