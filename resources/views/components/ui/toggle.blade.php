@props(['label', 'hint' => null])

{{-- Switch row: label (+ hint) on the left, iOS-style toggle on the right. wire:model / x-model on the input. --}}
<label class="flex items-center gap-3 min-h-11 cursor-pointer">
    <span class="flex-1 min-w-0">
        <span class="block text-[15px] font-medium text-ink">{{ $label }}</span>
        @if ($hint)<span class="block text-[13px] text-muted">{{ $hint }}</span>@endif
    </span>
    <input type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }} />
    <span class="relative h-7 w-12 shrink-0 rounded-full bg-field transition-colors peer-checked:bg-sage peer-focus-visible:ring-2 peer-focus-visible:ring-ink after:absolute after:left-0.5 after:top-0.5 after:size-6 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
</label>
