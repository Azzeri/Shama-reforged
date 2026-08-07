@props(['value', 'color' => 'gold'])

@php
$checkedClasses = $color === 'sage'
    ? 'peer-checked:bg-sage peer-checked:border-sage peer-checked:text-white'
    : 'peer-checked:bg-gold peer-checked:border-gold peer-checked:text-ink';
@endphp

<label class="cursor-pointer">
    <input type="checkbox" value="{{ $value }}" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span class="inline-flex rounded-full px-3.5 py-[7px] font-manrope text-[12.5px] font-bold border-[1.5px] border-ink/25 text-ink/60 transition-colors {{ $checkedClasses }}">
        {{ $slot }}
    </span>
</label>
