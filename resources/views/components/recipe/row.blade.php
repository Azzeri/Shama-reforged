@props(['recipe'])

{{-- Recipe list row: name, meal / main ingredient labels, rating dot with caption, packing bag, servings. --}}
<a href="{{ route('recipes.show', $recipe) }}" wire:navigate.hover {{ $attributes->merge(['class' => 'flex items-center gap-3 px-4 py-3 min-h-[60px]']) }}>
    <span class="flex-1 min-w-0 space-y-1.5">
        <span class="block font-fraunces text-[17px] font-semibold leading-snug text-ink">{{ $recipe->name }}</span>
        <x-recipe.labels :recipe="$recipe" />
    </span>
    @if ($recipe->servings > 1)
    <span class="shrink-0 text-[13px] text-muted">{{ \App\Models\Recipe::servingsLabel($recipe->servings) }}</span>
    @endif
    <x-ui.chevron-right class="size-4" />
</a>
