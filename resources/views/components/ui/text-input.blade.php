@props(['type' => 'text'])

<input
    type="{{ $type }}"
    @if ($type === 'number') inputmode="decimal" @endif
    {{ $attributes->merge(['class' => 'h-11 border border-field bg-white rounded-xl px-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink']) }}
/>
