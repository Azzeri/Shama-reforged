@props(['color' => 'ink', 'optional' => false])

<div {{ $attributes->merge(['class' => 'text-[11px] font-semibold uppercase tracking-[0.09em] mb-2 ' . ($color === 'forest' ? 'text-forest' : 'text-muted')]) }}>
    {{ $slot }}
    @if ($optional)
    <span class="normal-case tracking-normal font-medium text-muted">· {{ __('optional') }}</span>
    @endif
</div>
