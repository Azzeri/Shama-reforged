@props(['letter', 'prefix'])

{{-- Letter header inside a long list; the jump target of <x-ui.letter-bar> / <x-ui.letter-sheet>. --}}
<div id="{{ \App\Support\Alphabet::anchor($prefix, $letter) }}" {{ $attributes->merge(['class' => 'bg-letter px-3.5 py-[5px] text-[12px] font-semibold tracking-[0.04em] text-ink-soft scroll-mt-[calc(env(safe-area-inset-top)+3.5rem)]']) }}>{{ $letter }}</div>
