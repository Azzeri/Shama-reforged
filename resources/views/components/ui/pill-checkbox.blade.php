@props(['value', 'color' => 'gold'])

{{-- Checkbox styled as a chip (wire:model on the input). gold = meal, sage = main ingredient. --}}
@php
$checkedClasses = match ($color) {
    'sage' => 'peer-checked:bg-sage peer-checked:border-sage peer-checked:text-white',
    'ink' => 'peer-checked:bg-ink peer-checked:border-ink peer-checked:text-surface',
    default => 'peer-checked:bg-gold peer-checked:border-gold peer-checked:text-ink',
};
@endphp

<label class="cursor-pointer shrink-0">
    <input type="checkbox" value="{{ $value }}" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span class="inline-flex h-9 items-center gap-1.5 rounded-full px-3.5 text-[14px] font-medium border border-field bg-surface text-ink transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-ink {{ $checkedClasses }}">
        {{ $slot }}
    </span>
</label>
