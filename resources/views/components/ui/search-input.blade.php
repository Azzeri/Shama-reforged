@props(['label' => null, 'bg' => 'white'])

{{-- Search field with a magnifier icon and a visually hidden label. --}}
@php $id = $attributes->get('id') ?? 'search-' . \Illuminate\Support\Str::random(6); @endphp
<div class="relative">
    <x-icon.search class="absolute left-3 top-1/2 -translate-y-1/2 size-[18px] text-muted pointer-events-none" />
    <label for="{{ $id }}" class="sr-only">{{ $label ?? $attributes->get('placeholder') }}</label>
    <input
        id="{{ $id }}"
        type="search"
        {{ $attributes->except('id')->merge(['class' => 'w-full h-11 border border-field rounded-xl pl-10 pr-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink ' . ($bg === 'field' ? 'bg-field-bg' : 'bg-white')]) }} />
</div>
