@props(['recipe', 'compact' => false])

@php
$profiles = collect(\App\Models\Recipe::NUTRITION_PROFILES)
    ->filter(fn (string $profile) => $recipe->hasNutritionFor($profile))
    ->values();

$macroMeta = [
    'calories' => ['label' => 'kcal', 'unit' => '', 'chipBg' => 'bg-sand', 'text' => 'text-ink'],
    'protein' => ['label' => 'białko', 'unit' => 'g', 'chipBg' => 'bg-terracotta/10', 'text' => 'text-terracotta-dark'],
    'carbs' => ['label' => 'węgle', 'unit' => 'g', 'chipBg' => 'bg-sage/15', 'text' => 'text-forest'],
    'fat' => ['label' => 'tłuszcze', 'unit' => 'g', 'chipBg' => 'bg-gold/15', 'text' => 'text-gold-dark'],
];
@endphp

@if ($profiles->isNotEmpty())
<div {{ $attributes->merge(['class' => $compact ? 'space-y-1.5' : 'rounded-2xl border border-line bg-white p-4']) }}>
    @foreach ($profiles as $profile)
    @php $nutrition = $recipe->nutritionFor($profile); @endphp

    @if ($compact)
    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 rounded-xl bg-sand/70 px-3 py-2">
        <span class="font-inter text-[10.5px] font-semibold uppercase tracking-[0.06em] text-muted">
            {{ \App\Models\Recipe::nutritionProfileLabel($profile) }}
        </span>
        @foreach ($macroMeta as $key => $meta)
        @continue (blank($nutrition[$key]))
        <span class="font-inter text-[12px] font-semibold {{ $meta['text'] }} whitespace-nowrap">
            {{ $nutrition[$key] }}{{ $meta['unit'] }} <span class="font-semibold text-ink/40">{{ $meta['label'] }}</span>
        </span>
        @endforeach
    </div>
    @else
    <div @class(['pt-3 mt-3 border-t border-line' => ! $loop->first])>
        <span class="font-inter text-[13px] font-semibold text-muted">
            {{ \App\Models\Recipe::nutritionProfileLabel($profile) }}
        </span>

        <div class="mt-2 grid grid-cols-4 gap-2">
            @foreach ($macroMeta as $key => $meta)
            @continue (blank($nutrition[$key]))
            <div class="rounded-2xl {{ $meta['chipBg'] }} py-2.5 text-center">
                <div class="font-fraunces text-lg font-semibold leading-none {{ $meta['text'] }}">{{ $nutrition[$key] }}{{ $meta['unit'] }}</div>
                <div class="mt-1 font-inter text-[10.5px] text-muted">{{ $meta['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endforeach
</div>
@endif
