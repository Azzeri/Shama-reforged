@props(['href' => null])

@if ($href)
<a
    href="{{ $href }}"
    wire:navigate
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center size-10 rounded-2xl bg-white shadow-[0_1px_2px_rgba(43,33,24,0.06),0_6px_14px_rgba(43,33,24,0.07)] text-ink shrink-0']) }}>
    <flux:icon.arrow-left class="size-4" />
</a>
@else
<button
    type="button"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center size-10 rounded-2xl bg-white shadow-[0_1px_2px_rgba(43,33,24,0.06),0_6px_14px_rgba(43,33,24,0.07)] text-ink shrink-0']) }}>
    <flux:icon.arrow-left class="size-4" />
</button>
@endif
