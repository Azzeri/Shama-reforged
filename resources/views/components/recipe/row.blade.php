@props(['recipe', 'selected' => false])

{{-- Recipe list row: name, meal / main ingredient labels, rating dot with caption, packing bag, servings. --}}
<a href="{{ route('recipes.show', $recipe) }}" wire:navigate.hover {{ $attributes->merge(['class' => 'relative flex items-center gap-3 px-4 py-3 min-h-[60px] ' . ($selected ? 'lg:bg-[#FBF6F0] lg:before:absolute lg:before:inset-y-0 lg:before:left-0 lg:before:w-[3px] lg:before:bg-terracotta' : '')]) }}
    @if ($selected) aria-current="true" @endif>
    <span class="flex-1 min-w-0 space-y-1.5">
        <span class="block font-fraunces text-[17px] font-semibold leading-snug text-ink">{{ $recipe->name }}</span>
        <x-recipe.labels :recipe="$recipe" />
    </span>
    @if ($recipe->servings > 1)
    <span class="shrink-0 text-[13px] text-muted">{{ \App\Models\Recipe::servingsLabel($recipe->servings) }}</span>
    @endif
    <x-ui.chevron-right class="size-4" />
</a>
