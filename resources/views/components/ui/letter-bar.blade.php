@props(['letters', 'prefix', 'box'])

{{-- Desktop "Jump to:" row above a long list; scrolls the list's own box ($box = its id) to the letter. --}}
@if (count($letters) > 1)
<div {{ $attributes->merge(['class' => 'hidden lg:flex flex-wrap items-center gap-0.5']) }}>
    <span class="mr-1.5 text-[12px] text-muted">{{ __('Jump to:') }}</span>
    @foreach ($letters as $letter)
    <button type="button" wire:key="{{ $prefix }}-bar-{{ $letter }}" x-on:click="window.jumpToLetter('{{ \App\Support\Alphabet::anchor($prefix, $letter) }}', '{{ $box }}')"
        aria-label="{{ __('Jump to letter :letter', ['letter' => $letter]) }}"
        class="h-[30px] min-w-[30px] rounded-lg px-1.5 text-[13px] font-semibold text-ink hover:bg-sand">{{ $letter }}</button>
    @endforeach
</div>
@endif
