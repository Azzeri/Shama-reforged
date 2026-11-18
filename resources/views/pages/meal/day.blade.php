<?php

use App\Models\Meal;
use App\Models\Recipe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::app')] class extends Component {
    public string $date;

    public function render()
    {
        return $this->view()
            ->title($this->day->locale('en')->isoFormat('dddd, D MMMM'));
    }

    #[Computed]
    public function day(): CarbonInterface
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    #[Computed]
    public function meals(): Collection
    {
        return Meal::query()
            ->with('recipes')
            ->whereDate(Meal::DATE_COLUMN, $this->day->toDateString())
            ->get()
            ->sortBy(fn (Meal $meal) => array_search($meal->type, Meal::TYPES, true))
            ->values();
    }

    #[Computed]
    public function dayCalorieTotals(): array
    {
        return Recipe::calorieTotals($this->meals->flatMap->recipes);
    }

    public function mealCalorieTotals(Meal $meal): array
    {
        return Recipe::calorieTotals($meal->recipes);
    }

    public function recipeCalorieTotals(Recipe $recipe): array
    {
        return collect(Recipe::NUTRITION_PROFILES)
            ->mapWithKeys(fn (string $profile) => [$profile => $recipe->caloriesFor($profile)])
            ->filter(fn (?int $kcal) => filled($kcal))
            ->all();
    }
};
?>

<div>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <x-ui.back-button href="{{ route('meals.index') }}" :label="__('Week')" />

            <a
                href="{{ route('meals.day.edit', $date) }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 rounded-full border border-ink/10 bg-white px-4 py-2.5 font-manrope text-[15px] font-bold text-ink shrink-0">
                <flux:icon.pencil class="size-4" />
                {{ __('Edit') }}
            </a>
        </div>

        <h1 class="font-fraunces text-[28px] leading-tight font-semibold text-ink">{{ $this->day->locale('en')->isoFormat('dddd, D MMMM') }}</h1>

        <x-meal.week-strip :day="$this->day" route="meals.day" />

        @if ($this->dayCalorieTotals)
        <div class="flex items-center justify-between gap-3 rounded-[18px] bg-sand px-4 py-3.5">
            <span class="font-manrope text-[14px] font-semibold text-ink/70">{{ __('Total for the day') }}</span>
            <div class="flex gap-5">
                @foreach ($this->dayCalorieTotals as $profile => $kcal)
                @php $label = Recipe::nutritionProfileLabel($profile); @endphp
                <div class="text-right">
                    <div class="font-manrope text-[12px] text-ink/60">{{ $label }} ({{ mb_substr($label, 0, 1) }})</div>
                    <div class="font-fraunces text-[22px] font-bold leading-tight text-ink">{{ $kcal }} <span class="font-manrope text-[13px] font-medium">kcal</span></div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if ($this->meals->isNotEmpty())
        <div class="bg-white rounded-[22px] border border-ink/10 divide-y divide-ink/8">
            @foreach ($this->meals as $meal)
            <div wire:key="meal-{{ $meal->id }}" class="px-4 py-3.5 space-y-2">
                <div class="font-manrope text-[12px] font-extrabold uppercase tracking-[0.12em] text-ink/60">{{ Meal::typeLabel($meal->type) }}</div>

                @foreach ($meal->recipes as $recipe)
                <div
                    wire:key="meal-{{ $meal->id }}-recipe-{{ $recipe->id }}"
                    x-data
                    x-on:click="window.openRecipe('{{ route('recipes.show', $recipe) }}')"
                    class="cursor-pointer">
                    <div class="font-manrope text-[16px] font-semibold text-ink">{{ $recipe->name }}</div>
                    @if ($line = Recipe::shortCalorieLine($this->recipeCalorieTotals($recipe)))
                    <div class="font-manrope text-[13.5px] text-ink/55">{{ $line }}</div>
                    @endif
                </div>
                @endforeach

                @if (filled($meal->note))
                <div class="flex items-center gap-2.5">
                    <flux:icon.document class="size-4 text-ink/50 shrink-0" />
                    <span class="font-fraunces italic text-[15.5px] text-ink/80">{{ $meal->note }}</span>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <a
            href="{{ route('meals.day.edit', $date) }}"
            wire:navigate
            class="block border border-dashed border-ink/24 rounded-[18px] px-4 py-8 text-center font-manrope text-sm text-ink/40">
            {{ __('No meals for this day.') }}
        </a>
        @endif
    </div>
</div>
