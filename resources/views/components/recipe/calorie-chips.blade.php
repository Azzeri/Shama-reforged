@props(['totals', 'suffix' => null])

@if (! empty($totals))
<div {{ $attributes->merge(['class' => 'flex gap-2']) }}>
    @foreach ($totals as $profile => $kcal)
    <div class="flex-1 rounded-2xl bg-sand py-3 text-center">
        <div class="font-fraunces text-lg font-bold leading-none text-ink">{{ $kcal }} kcal</div>
        <div class="mt-1 font-manrope text-[11px] text-ink/50">
            {{ \App\Models\Recipe::nutritionProfileLabel($profile) }}{{ $suffix ? ' ' . $suffix : '' }}
        </div>
    </div>
    @endforeach
</div>
@endif
