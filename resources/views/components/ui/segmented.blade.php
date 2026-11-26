@props(['options' => [], 'model' => null, 'value' => null, 'size' => 'md'])

{{--
    Segmented control. $options: value => label. Bound either to a Livewire
    property ($model, uses wire:click="$set") or, without $model, renders
    links/buttons from the slot.
--}}
<div role="tablist" {{ $attributes->merge(['class' => 'flex rounded-[14px] bg-sand p-1 gap-1']) }}>
    @if ($model)
    @foreach ($options as $optionValue => $label)
    <button
        type="button"
        role="tab"
        aria-selected="{{ (string) $value === (string) $optionValue ? 'true' : 'false' }}"
        wire:click="$set('{{ $model }}', '{{ $optionValue }}')"
        @class([
            'flex-1 min-w-0 rounded-[10px] px-2 text-[14px] font-medium truncate transition-colors',
            $size === 'sm' ? 'h-9' : 'h-10',
            'bg-surface text-ink shadow-[0_1px_2px_rgba(31,27,22,0.12)] font-semibold' => (string) $value === (string) $optionValue,
            'text-muted' => (string) $value !== (string) $optionValue,
        ])>{{ $label }}</button>
    @endforeach
    @else
    {{ $slot }}
    @endif
</div>
