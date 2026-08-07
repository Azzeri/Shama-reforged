@props(['totals'])

@if (! empty($totals))
<div {{ $attributes->merge(['class' => 'font-manrope text-[12.5px] text-ink/50']) }}>
    {{ collect($totals)->map(fn ($kcal, $profile) => \App\Models\Recipe::nutritionProfileLabel($profile) . ' ' . $kcal . ' kcal')->implode(' · ') }}
</div>
@endif
