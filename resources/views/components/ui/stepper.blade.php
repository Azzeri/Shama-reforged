@props(['model' => null, 'value', 'min' => 1, 'max' => null, 'label', 'decrement' => null, 'increment' => null])

{{-- "− 2 porcje +" stepper. Either Livewire ($model or explicit actions) or Alpine (attributes on the slot). --}}
<div {{ $attributes->merge(['class' => 'inline-flex h-11 items-center rounded-full border border-field bg-surface']) }}>
    <button type="button"
        @if ($decrement) wire:click="{{ $decrement }}" @elseif ($model) wire:click="$set('{{ $model }}', {{ max($min, $value - 1) }})" @endif
        @disabled($value <= $min)
        aria-label="{{ __('Decrease') }}"
        class="flex size-11 items-center justify-center text-ink disabled:text-faint">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>
    </button>
    <span class="min-w-[4.5rem] text-center text-[14px] font-semibold text-ink tabular-nums" aria-live="polite">{{ $label }}</span>
    <button type="button"
        @if ($increment) wire:click="{{ $increment }}" @elseif ($model) wire:click="$set('{{ $model }}', {{ $max !== null ? min($max, $value + 1) : $value + 1 }})" @endif
        @disabled($max !== null && $value >= $max)
        aria-label="{{ __('Increase') }}"
        class="flex size-11 items-center justify-center text-ink disabled:text-faint">
        <x-icon.plus class="size-4" />
    </button>
</div>
