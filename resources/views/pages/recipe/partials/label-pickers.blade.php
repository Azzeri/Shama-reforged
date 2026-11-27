{{-- "To fill in" sheet/card: chips for the fields that were missing when the recipe was opened. Each tap saves. --}}
@use('App\Models\Recipe')
@php
    $groupLabel = 'block mb-1.5 text-[11px] font-semibold uppercase tracking-[0.09em] text-muted';
    $tagIds = $recipe->tags->modelKeys();
@endphp
<div class="space-y-3.5">
    @foreach ($fields as $field)
    <fieldset wire:key="picker-{{ $field }}">
        <legend class="{{ $groupLabel }}">{{ Recipe::labelFieldName($field) }}</legend>
        <div class="flex flex-wrap gap-1.5">
            @if ($field === Recipe::FIELD_MEAL)
            @foreach ($this->mealTags as $tag)
            <x-ui.chip wire:key="pick-meal-{{ $tag->id }}" color="gold" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
            @endforeach
            @elseif ($field === Recipe::FIELD_MAIN_INGREDIENT)
            @foreach ($this->dietTags as $tag)
            <x-ui.chip wire:key="pick-diet-{{ $tag->id }}" color="sage" :active="in_array($tag->id, $tagIds)" wire:click="toggleTag({{ $tag->id }})">{{ $tag->label() }}</x-ui.chip>
            @endforeach
            @elseif ($field === Recipe::FIELD_RATING)
            @foreach (Recipe::RATINGS as $rating)
            <x-ui.chip wire:key="pick-rating-{{ $rating }}" :active="$recipe->rating === $rating" wire:click="setRating('{{ $rating }}')">
                <span class="size-2 rounded-full {{ Recipe::ratingDotClass($rating) }}" aria-hidden="true"></span>
                {{ Recipe::ratingLabel($rating) }}
            </x-ui.chip>
            @endforeach
            @else
            <x-ui.chip :active="$recipe->packable === true" wire:click="setPackable(true)">{{ __('Yes, can be taken along') }}</x-ui.chip>
            <x-ui.chip :active="$recipe->packable === false" wire:click="setPackable(false)">{{ __('No') }}</x-ui.chip>
            @endif
        </div>
    </fieldset>
    @endforeach
</div>
