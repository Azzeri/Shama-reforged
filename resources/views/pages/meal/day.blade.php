<?php

use App\Models\Meal;
use App\Models\Recipe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    public string $date;

    public ?int $mealToDelete = null;

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
            ->orderBy(Meal::DATE_COLUMN)
            ->get();
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

    public function goToRecipe(Recipe $recipe): void
    {
        $this->redirect($recipe->showUrlWithBack());
    }

    public function goToEditDay(): void
    {
        $this->redirectRoute('meals.day.edit', $this->date);
    }

    public function confirmDelete(int $mealId): void
    {
        $this->mealToDelete = $mealId;
    }

    public function delete(): void
    {
        Meal::query()->whereKey($this->mealToDelete)->delete();
        $this->mealToDelete = null;
        unset($this->meals, $this->dayCalorieTotals);

        Flux::toast(variant: 'success', text: __('Meal deleted.'));
    }
};
?>

<div>
    <div class="space-y-5">
        <x-ui.back-button href="{{ route('meals.index') }}" />

        <div class="flex items-center justify-between gap-3">
            <h1 class="font-fraunces text-2xl font-semibold text-ink">{{ $this->day->locale('en')->isoFormat('dddd, D MMMM') }}</h1>

            <a
                href="{{ route('meals.day.edit', $date) }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 rounded-full border-[1.5px] border-ink/15 bg-white px-4 py-2 font-manrope text-[13px] font-bold text-ink shrink-0">
                <flux:icon.pencil class="size-3.5" />
                {{ __('Edit day') }}
            </a>
        </div>

        <x-recipe.calorie-chips :totals="$this->dayCalorieTotals" suffix="razem" />

        <div class="space-y-3">
            @forelse ($this->meals as $meal)
            <div wire:key="meal-{{ $meal->id }}" wire:click="goToEditDay" class="bg-white rounded-[18px] p-4 border border-ink/10 cursor-pointer">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="font-manrope text-[11px] font-extrabold uppercase tracking-[0.1em] text-ink/50">
                        {{ Meal::typeLabel($meal->type) }}
                    </span>

                    <div @click.stop>
                        <flux:modal.trigger name="delete-meal-modal">
                            <button type="button" wire:click="confirmDelete({{ $meal->id }})" class="text-terracotta-dark p-1">
                                <flux:icon.trash class="size-4" />
                            </button>
                        </flux:modal.trigger>
                    </div>
                </div>

                @if (filled($meal->note))
                <div class="bg-sand rounded-xl px-3.5 py-2.5 mb-2 font-fraunces italic text-[13.5px] text-ink/70">
                    {{ $meal->note }}
                </div>
                @endif

                @forelse ($meal->recipes as $recipe)
                <div wire:click.stop="goToRecipe({{ $recipe->id }})" class="border border-ink/10 rounded-xl px-3.5 py-2.5 mb-2 cursor-pointer hover:bg-sand/40 transition-colors">
                    <div class="font-manrope text-sm text-ink">
                        {{ $recipe->name }}
                    </div>
                    <x-recipe.calorie-line :totals="$this->recipeCalorieTotals($recipe)" class="mt-0.5" />
                </div>
                @empty
                @if (blank($meal->note))
                <div class="border border-dashed border-ink/24 rounded-xl px-3.5 py-4 text-center font-manrope text-[13px] text-ink/40">
                    {{ __('No recipes') }}
                </div>
                @endif
                @endforelse

                <x-recipe.calorie-chips :totals="$this->mealCalorieTotals($meal)" class="mt-1" />
            </div>
            @empty
            <div class="border border-dashed border-ink/24 rounded-[18px] px-4 py-8 text-center font-manrope text-sm text-ink/40">
                {{ __('No meals for this day.') }}
            </div>
            @endforelse
        </div>
    </div>

    <flux:modal name="delete-meal-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Delete meal?') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-sm text-ink/60 -mt-4">
                {{ __('This action cannot be undone.') }}
            </p>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 hover:text-ink px-4 py-2.5">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>

                <flux:modal.close>
                    <button
                        type="button"
                        wire:click="delete"
                        class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">
                        {{ __('Delete meal') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
