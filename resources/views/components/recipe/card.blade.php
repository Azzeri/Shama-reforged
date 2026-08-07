@props(['recipe', 'dot' => 'terracotta'])

@php
$dotClasses = match ($dot) {
    'gold' => 'bg-gold shadow-[0_0_0_3px_rgba(217,164,65,0.18)]',
    'sage' => 'bg-sage shadow-[0_0_0_3px_rgba(92,122,94,0.16)]',
    default => 'bg-terracotta shadow-[0_0_0_3px_rgba(193,68,45,0.15)]',
};
@endphp

<div
    wire:click="goToDetails({{ $recipe->id }})"
    class="relative bg-white rounded-[18px] p-4 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_10px_24px_rgba(43,33,24,0.07)] border border-ink/5 cursor-pointer"
>
    <span class="absolute top-4 right-4 size-2.5 rounded-full {{ $dotClasses }}"></span>

    <h3 class="font-fraunces text-lg font-semibold text-ink mr-6 mb-1.5">
        {{ $recipe->name ?? __('Unknown recipe') }}
    </h3>

    <p class="font-manrope text-[12.5px] text-ink/60 leading-relaxed mb-2.5">
        {{ __('Składniki') }}: {{ $recipe->ingredients->take(10)->pluck('name')->join(', ') ?: '-' }}
    </p>

    <div class="flex flex-wrap gap-1.5">
        @foreach ($recipe->tags as $tag)
        <x-recipe.tag-badge :tag="$tag" />
        @endforeach
    </div>
</div>
