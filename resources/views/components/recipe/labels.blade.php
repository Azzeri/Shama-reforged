@props(['recipe'])

{{-- Meal labels (yellow), main ingredient (green), rating dot + caption, packing bag. --}}
@php
    $mealOrder = array_flip(\App\Models\Meal::orderedTagNames());
    $tags = $recipe->tags->sortBy(fn ($tag) => [$tag->isMealType() ? 0 : 1, $mealOrder[$tag->name] ?? 99]);
@endphp
@if ($tags->isNotEmpty() || $recipe->rating || $recipe->packable)
<span {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
    @foreach ($tags as $tag)
    <span @class(['rounded-full px-2 py-0.5 text-[12px] font-semibold', 'bg-gold-light text-gold-dark' => $tag->isMealType(), 'bg-sage-light text-forest' => ! $tag->isMealType()])>{{ $tag->label() }}</span>
    @endforeach
    @if ($recipe->rating)
    <span class="inline-flex items-center gap-1 text-[12.5px] text-ink-soft">
        <span class="size-2 rounded-full {{ \App\Models\Recipe::ratingDotClass($recipe->rating) }}" aria-hidden="true"></span>
        {{ \App\Models\Recipe::ratingLabel($recipe->rating) }}
    </span>
    @endif
    @if ($recipe->packable)
    <span class="inline-flex text-ink-soft" title="{{ __('for packing') }}">
        <x-icon.bag class="size-4" />
        <span class="sr-only">{{ __('for packing') }}</span>
    </span>
    @endif
</span>
@endif
