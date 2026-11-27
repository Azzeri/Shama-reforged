{{-- "To fill in" row: the recipe's labels, a dashed "+ field" for each one missing. --}}
@use('App\Models\Recipe')
@php
    $mealOrder = array_flip(\App\Models\Meal::orderedTagNames());
    $missing = $recipe->missingLabels();
    $mealTags = $recipe->tags->filter->isMealType()->sortBy(fn ($tag) => $mealOrder[$tag->name] ?? 99);
    $dietTags = $recipe->tags->filter->isDietType();
    $placeholder = 'rounded-full border border-dashed border-faint px-2 py-0.5 text-[11px] font-semibold text-ink-soft';
@endphp
<span class="flex flex-wrap items-center gap-1.5">
    @forelse ($mealTags as $tag)
    <span class="rounded-full bg-gold-light px-2 py-0.5 text-[11px] font-semibold text-gold-dark">{{ $tag->label() }}</span>
    @empty
    <span class="{{ $placeholder }}">+ {{ Recipe::labelFieldShort(Recipe::FIELD_MEAL) }}</span>
    @endforelse
    @forelse ($dietTags as $tag)
    <span class="rounded-full bg-sage-light px-2 py-0.5 text-[11px] font-semibold text-forest">{{ $tag->label() }}</span>
    @empty
    <span class="{{ $placeholder }}">+ {{ Recipe::labelFieldShort(Recipe::FIELD_MAIN_INGREDIENT) }}</span>
    @endforelse
    @if ($recipe->rating)
    <span class="inline-flex items-center gap-1 px-1 text-[11px] font-semibold text-ink-soft">
        <span class="size-2 rounded-full {{ Recipe::ratingDotClass($recipe->rating) }}" aria-hidden="true"></span>{{ Recipe::ratingLabel($recipe->rating) }}
    </span>
    @else
    <span class="{{ $placeholder }}">+ {{ Recipe::labelFieldShort(Recipe::FIELD_RATING) }}</span>
    @endif
    @if ($recipe->packable === null)
    <span class="{{ $placeholder }}">+ {{ Recipe::labelFieldShort(Recipe::FIELD_PACKABLE) }}</span>
    @else
    <span class="rounded-full bg-divider px-2 py-0.5 text-[11px] font-semibold text-ink-soft">{{ $recipe->packable ? __('for packing') : __('not for packing') }}</span>
    @endif
</span>
