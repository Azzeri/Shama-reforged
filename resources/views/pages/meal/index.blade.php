<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\ShoppingList;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    #[Url(as: 'week')]
    public ?string $week = null;

    public bool $showPastDays = false;

    public array $selectedDaysForShoppingList = [];

    public function render()
    {
        return $this->view()
            ->title(__('Meal calendar'));
    }

    /**
     * The calendar shows a rolling 7-day window starting at this date (today
     * by default), so the next days are always plannable — e.g. on Sunday
     * the whole upcoming week is visible. `?week=` holds the window start.
     */
    #[Computed]
    public function rangeStart(): CarbonInterface
    {
        return ($this->week ? Carbon::parse($this->week) : now())->copy()->startOfDay();
    }

    #[Computed]
    public function rangeEnd(): CarbonInterface
    {
        return $this->rangeStart->copy()->addDays(6)->endOfDay();
    }

    #[Computed]
    public function isCurrentRange(): bool
    {
        return $this->rangeStart->isToday();
    }

    /**
     * Past days of the current calendar week, shown on demand above today.
     */
    #[Computed]
    public function pastDays(): Collection
    {
        $today = now()->startOfDay();

        return $this->buildDays($today->copy()->startOfWeek(), $today->copy()->subDay());
    }

    #[Computed]
    public function days(): Collection
    {
        return $this->buildDays($this->rangeStart, $this->rangeEnd);
    }

    private function buildDays(CarbonInterface $start, CarbonInterface $end): Collection
    {
        if ($start->gt($end)) {
            return collect();
        }

        $meals = Meal::query()
            ->with('recipes:id,name,calories_me,calories_wife')
            ->whereBetween(Meal::DATE_COLUMN, [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy(Meal::DATE_COLUMN)
            ->get();

        $dayCount = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        return collect(range(0, $dayCount - 1))->map(function (int $offset) use ($start, $meals) {
            $date = $start->copy()->startOfDay()->addDays($offset);

            return [
                'date' => $date,
                'isToday' => $date->isToday(),
                'isPast' => $date->copy()->startOfDay()->lt(now()->startOfDay()),
                'meals' => $meals
                    ->filter(fn (Meal $meal) => $meal->date->isSameDay($date))
                    ->sortBy(fn (Meal $meal) => array_search($meal->type, Meal::TYPES, true))
                    ->values(),
            ];
        });
    }

    #[Computed]
    public function visibleDays(): Collection
    {
        if ($this->isCurrentRange && $this->showPastDays) {
            return $this->pastDays->concat($this->days)->values();
        }

        return $this->days;
    }

    public function mealCalorieTotals(Meal $meal): array
    {
        return Recipe::calorieTotals($meal->recipes);
    }

    public function dayCalorieTotals(Collection $meals): array
    {
        return Recipe::calorieTotals($meals->flatMap->recipes);
    }

    public function goToToday(): void
    {
        $this->week = null;
        unset($this->rangeStart, $this->rangeEnd);
    }

    public function goToPreviousWeek(): void
    {
        $this->shiftRange(-7);
    }

    public function goToNextWeek(): void
    {
        $this->shiftRange(7);
    }

    private function shiftRange(int $days): void
    {
        $start = $this->rangeStart->copy()->addDays($days);
        $this->week = $start->isToday() ? null : $start->toDateString();
        unset($this->rangeStart, $this->rangeEnd);
    }

    public function togglePastDays(): void
    {
        $this->showPastDays = ! $this->showPastDays;
    }

    public function generateShoppingListFromSelectedDays(): void
    {
        $this->generateShoppingListItems($this->selectedDaysForShoppingList);
    }

    public function generateShoppingListFromFullWeek(): void
    {
        $dates = collect(range(0, 6))
            ->map(fn (int $offset) => $this->rangeStart->copy()->addDays($offset)->toDateString())
            ->all();

        $this->generateShoppingListItems($dates);
    }

    private function generateShoppingListItems(array $dateStrings): void
    {
        $dates = collect($dateStrings)
            ->map(fn (string $date) => Carbon::parse($date)->startOfDay())
            ->filter(fn (CarbonInterface $date) => $date->betweenIncluded($this->rangeStart, $this->rangeEnd))
            ->values();

        if ($dates->isEmpty()) {
            return;
        }

        $meals = Meal::query()
            ->with(['recipes' => fn ($query) => $query->with('ingredients:id,name')])
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate(Meal::DATE_COLUMN, $date->toDateString());
                }
            })
            ->get();

        $shoppingList = ShoppingList::query()->firstOrCreate(['id' => 1], ['name' => 'Main shopping list']);

        $addedCount = 0;

        foreach ($meals as $meal) {
            foreach ($meal->recipes as $recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $quantity = trim((string) ($ingredient->pivot?->quantity ?? ''));
                    $amount = $ingredient->pivot?->totalAmount();

                    if ($quantity === '' && $amount === null) {
                        continue;
                    }

                    $shoppingList->items()->create([
                        'name' => $ingredient->name,
                        'quantity' => $quantity !== '' ? $quantity : null,
                        'amount' => $amount,
                        'unit' => $ingredient->pivot?->unit,
                        'is_checked' => false,
                        'week_day' => strtolower($meal->date->englishDayOfWeek),
                        'recipe_id' => $recipe->id,
                        'meal_id' => $meal->id,
                        'ingredient_id' => $ingredient->id,
                    ]);

                    $addedCount++;
                }
            }
        }

        $this->selectedDaysForShoppingList = [];

        Flux::toast(
            variant: 'success',
            text: $addedCount > 0
                ? __(':count items added to shopping list.', ['count' => $addedCount])
                : __('No new items to add.'),
        );

        $this->redirectRoute('shopping-list.index', navigate: true);
    }
};
?>

<div>
    <div class="space-y-4">
        <div class="bg-white rounded-[18px] p-[18px] shadow-[0_1px_2px_rgba(43,33,24,0.06),0_10px_24px_rgba(43,33,24,0.07)]">
            <h1 class="font-fraunces text-2xl font-semibold text-ink mb-4">{{ __('Meal calendar') }}</h1>

            <div class="flex items-center gap-2 bg-sand rounded-xl py-1.5 pr-1.5 pl-4 mb-3.5">
                <span class="flex-1 font-manrope text-sm font-bold text-ink">
                    {{ $this->rangeStart->copy()->locale('en')->isoFormat('D MMMM') }} – {{ $this->rangeEnd->copy()->locale('en')->isoFormat('D MMMM YYYY') }}
                </span>
                <button
                    type="button"
                    wire:click="goToPreviousWeek"
                    class="flex items-center justify-center w-8 h-8 rounded-[9px] bg-white text-ink">
                    <flux:icon.chevron-left class="size-4" />
                </button>
                <button
                    type="button"
                    wire:click="goToNextWeek"
                    class="flex items-center justify-center w-8 h-8 rounded-[9px] bg-white text-ink">
                    <flux:icon.chevron-right class="size-4" />
                </button>
            </div>

            <div class="flex items-center justify-between mb-3.5">
                <button
                    type="button"
                    wire:click="goToToday"
                    class="inline-flex items-center gap-1.5 bg-terracotta text-white rounded-[10px] px-3.5 py-2 font-manrope text-[12.5px] font-extrabold">
                    📅 {{ __('Today') }}
                </button>

                @if ($this->isCurrentRange)
                <button type="button" wire:click="togglePastDays" class="font-manrope text-[13px] font-bold text-forest">
                    {{ $showPastDays ? __('Hide past days') : __('Show past days') }}
                </button>
                @endif
            </div>

            <flux:modal.trigger name="generate-shopping-list-modal">
                <button
                    type="button"
                    class="w-full bg-transparent border-[1.5px] border-terracotta text-terracotta-dark rounded-xl px-3.5 py-[11px] font-manrope text-[13.5px] font-extrabold">
                    🛒 {{ __('Generate shopping list') }}
                </button>
            </flux:modal.trigger>
        </div>

        @foreach ($this->visibleDays as $dayData)
            <a
                href="{{ route('meals.day', $dayData['date']->toDateString()) }}"
                wire:navigate
                wire:key="day-{{ $dayData['date']->toDateString() }}"
                class="block bg-white rounded-[18px] p-4 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_10px_24px_rgba(43,33,24,0.07)] cursor-pointer">
                <div class="flex justify-between items-start mb-2.5">
                    <div>
                        <div class="font-manrope text-[11px] font-extrabold uppercase tracking-[0.1em] text-ink/50">
                            {{ $dayData['date']->copy()->locale('en')->isoFormat('dddd') }}
                        </div>
                        <div class="font-fraunces text-2xl font-bold {{ $dayData['isToday'] ? 'text-forest' : 'text-ink' }}">
                            {{ $dayData['date']->format('d') }}
                        </div>
                    </div>

                    @if ($dayData['isToday'])
                    <span class="bg-sage/18 text-forest font-manrope text-xs font-bold px-2.5 py-1 rounded-full">{{ __('Today') }}</span>
                    @endif
                </div>

                @forelse ($dayData['meals'] as $meal)
                <div wire:key="meal-{{ $meal->id }}" class="border border-ink/10 rounded-xl px-3 py-2.5 mb-2">
                    <div class="font-manrope text-xs font-bold uppercase tracking-[0.05em] text-ink/50 mb-0.5">
                        {{ Meal::typeLabel($meal->type) }}
                    </div>
                    <div class="font-manrope text-sm text-ink">
                        @forelse ($meal->recipes as $recipe)
                        <span
                            x-data
                            x-on:mousedown.stop
                            x-on:click.prevent.stop="window.openRecipe('{{ route('recipes.show', $recipe) }}')"
                            class="hover:underline hover:text-terracotta-dark">{{ $recipe->name }}</span>{{ !$loop->last ? ', ' : '' }}
                        @empty
                        @if (filled($meal->note))
                        <span class="font-fraunces italic text-ink/60">{{ $meal->note }}</span>
                        @else
                        {{ __('No recipes') }}
                        @endif
                        @endforelse
                    </div>
                    <x-recipe.calorie-line :totals="$this->mealCalorieTotals($meal)" class="mt-1" />
                </div>
                @empty
                <div class="border border-dashed border-ink/24 rounded-xl py-6 text-center font-manrope text-[13px] text-ink/40">
                    {{ __('No meals') }}
                </div>
                @endforelse

                <x-recipe.calorie-chips :totals="$this->dayCalorieTotals($dayData['meals'])" suffix="razem" class="mt-1" />
            </a>
        @endforeach
    </div>

    <flux:modal name="generate-shopping-list-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Generate shopping list') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <p class="font-manrope text-[13px] text-ink/60">{{ __('Select the days to collect ingredients from assigned recipes.') }}</p>

            <div class="space-y-4">
                <div class="space-y-2">
                    @foreach ($this->days as $dayData)
                    <label wire:key="generate-day-{{ $dayData['date']->toDateString() }}" class="flex items-center gap-2.5 border-[1.5px] border-ink/15 rounded-xl px-3.5 py-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="selectedDaysForShoppingList" value="{{ $dayData['date']->toDateString() }}" class="peer sr-only">
                        <span class="flex items-center justify-center size-5 rounded-[6px] border-2 border-ink/15 text-transparent peer-checked:bg-sage peer-checked:border-sage peer-checked:text-white text-xs font-bold shrink-0 transition-colors">✓</span>
                        <span class="font-manrope text-sm font-semibold text-ink">{{ $dayData['date']->copy()->locale('en')->isoFormat('dddd D.MM') }}</span>
                    </label>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="generateShoppingListFromSelectedDays"
                    wire:target="generateShoppingListFromSelectedDays"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] py-3.5 font-manrope text-sm font-extrabold disabled:opacity-60">
                    <flux:icon.loading wire:loading wire:target="generateShoppingListFromSelectedDays" variant="mini" />
                    <span wire:loading.remove wire:target="generateShoppingListFromSelectedDays">+ {{ __('Add from selected days') }}</span>
                </button>
            </div>

            <button
                type="button"
                wire:click="generateShoppingListFromFullWeek"
                wire:target="generateShoppingListFromFullWeek"
                wire:loading.attr="disabled"
                class="w-full flex items-center justify-center gap-2 bg-transparent border-[1.5px] border-ink/20 text-ink rounded-[14px] py-3.5 font-manrope text-sm font-extrabold disabled:opacity-60">
                <flux:icon.loading wire:loading wire:target="generateShoppingListFromFullWeek" variant="mini" />
                <span wire:loading.remove wire:target="generateShoppingListFromFullWeek">📅 {{ __('Add from all 7 days') }}</span>
            </button>
        </div>
    </flux:modal>
</div>
