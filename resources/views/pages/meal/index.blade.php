<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Actions\ShoppingList\GenerateShoppingList;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

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
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->values()
            ->all();

        if (empty($dates)) {
            return;
        }

        app(GenerateShoppingList::class)($dates);

        $this->selectedDaysForShoppingList = [];

        $this->redirectRoute('shopping-list.index', navigate: true);
    }
};
?>

<div>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h1 class="font-fraunces text-[34px] font-semibold leading-none text-ink">{{ __('Plan') }}</h1>

            <flux:modal.trigger name="generate-shopping-list-modal">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border-[1.5px] border-terracotta bg-white px-4 py-2.5 font-inter text-[14.5px] font-semibold text-terracotta-dark">
                    <flux:icon.shopping-cart class="size-4" />
                    {{ __('Shopping list') }}
                </button>
            </flux:modal.trigger>
        </div>

        <div class="flex items-center gap-2.5">
            <div class="flex-1 flex items-center justify-between rounded-2xl bg-sand px-1.5 py-1.5">
                <button type="button" wire:click="goToPreviousWeek" class="flex items-center justify-center size-9 text-ink" aria-label="{{ __('Previous') }}">
                    <flux:icon.chevron-left class="size-5" />
                </button>
                <span class="font-inter text-[15.5px] font-semibold text-ink">
                    @if ($this->rangeStart->isSameMonth($this->rangeEnd))
                    {{ $this->rangeStart->format('j') }} – {{ $this->rangeEnd->copy()->locale(app()->getLocale())->isoFormat('D MMMM') }}
                    @else
                    {{ $this->rangeStart->copy()->locale(app()->getLocale())->isoFormat('D MMM') }} – {{ $this->rangeEnd->copy()->locale(app()->getLocale())->isoFormat('D MMM') }}
                    @endif
                </span>
                <button type="button" wire:click="goToNextWeek" class="flex items-center justify-center size-9 text-ink" aria-label="{{ __('Next') }}">
                    <flux:icon.chevron-right class="size-5" />
                </button>
            </div>

            <button
                type="button"
                wire:click="goToToday"
                class="shrink-0 rounded-2xl border border-line bg-white px-4 py-3 font-inter text-[15px] font-semibold text-ink">
                {{ __('Today') }}
            </button>
        </div>

        @if ($this->isCurrentRange)
        <div class="flex justify-center">
            <button type="button" wire:click="togglePastDays" class="inline-flex items-center gap-1.5 py-1 font-inter text-[14px] text-muted">
                @if ($showPastDays)
                <flux:icon.chevron-down class="size-4" />
                {{ __('Hide past days') }}
                @else
                <flux:icon.chevron-up class="size-4" />
                {{ __('Show past days') }}
                @endif
            </button>
        </div>
        @endif

        @foreach ($this->visibleDays as $dayData)
        @php
            $dateString = $dayData['date']->toDateString();
            $dayTotals = $this->dayCalorieTotals($dayData['meals']);
        @endphp
        <div
            wire:key="day-{{ $dateString }}"
            @class([
                'bg-white rounded-[22px] border overflow-hidden',
                'border-terracotta/45' => $dayData['isToday'],
                'border-line' => ! $dayData['isToday'],
                'opacity-60' => $dayData['isPast'],
            ])>
            <div class="flex items-center gap-2.5 px-4 py-3.5">
                <a href="{{ route('meals.day', $dateString) }}" wire:navigate class="flex items-center gap-2.5 min-w-0">
                    <span class="font-fraunces text-[28px] font-semibold leading-none text-ink">{{ $dayData['date']->format('j') }}</span>
                    <span class="font-inter text-[16px] font-semibold text-ink truncate">{{ \Illuminate\Support\Str::ucfirst($dayData['date']->copy()->locale(app()->getLocale())->isoFormat('dddd')) }}</span>
                    @if ($dayData['isToday'])
                    <span class="shrink-0 rounded-full bg-terracotta/12 px-2.5 py-0.5 font-inter text-[12.5px] font-semibold text-terracotta-dark">{{ __('Today') }}</span>
                    @endif
                </a>

                <div class="ml-auto flex items-center gap-3 shrink-0">
                    @if ($dayData['meals']->isEmpty())
                    <a href="{{ route('meals.day.edit', $dateString) }}" wire:navigate class="inline-flex items-center gap-1.5 font-inter text-[15px] font-semibold text-terracotta-dark">
                        <flux:icon.plus class="size-4" />
                        {{ __('Plan it') }}
                    </a>
                    @else
                    @if ($dayTotals)
                    <span class="font-inter text-[13.5px] text-muted">{{ Recipe::shortCalorieLine($dayTotals) }}</span>
                    @endif
                    <a href="{{ route('meals.day.edit', $dateString) }}" wire:navigate class="text-muted p-1 -m-1" aria-label="{{ __('Edit day') }}">
                        <flux:icon.pencil class="size-[18px]" />
                    </a>
                    @endif
                </div>
            </div>

            @if ($dayData['meals']->isNotEmpty())
            <div class="divide-y divide-divider border-t border-line">
                @foreach ($dayData['meals'] as $meal)
                <div wire:key="meal-{{ $meal->id }}" class="flex gap-3 px-4">
                    <div class="w-[88px] shrink-0 py-3 font-inter text-[14px] text-muted">{{ Meal::typeLabel($meal->type) }}</div>

                    <div class="flex-1 min-w-0">
                        @foreach ($meal->recipes as $recipe)
                        <button
                            type="button"
                            wire:key="meal-{{ $meal->id }}-recipe-{{ $recipe->id }}"
                            x-data
                            x-on:click="window.openRecipe('{{ route('recipes.show', $recipe) }}')"
                            class="w-full flex items-center gap-2 py-3 text-left">
                            <span class="flex-1 font-inter text-[15.5px] text-ink">{{ $recipe->name }}</span>
                            <x-ui.chevron-right />
                        </button>
                        @endforeach

                        @if (filled($meal->note))
                        <a href="{{ route('meals.day', $dateString) }}" wire:navigate class="flex items-center gap-2 py-3">
                            <flux:icon.document class="size-4 text-muted shrink-0" />
                            <span class="font-fraunces italic text-[15px] text-muted">{{ $meal->note }}</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>

    <flux:modal name="generate-shopping-list-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Generate shopping list') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-muted p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <p class="font-inter text-[13px] text-muted">{{ __('Select the days to collect ingredients from assigned recipes.') }}</p>
            <p class="font-inter text-[12.5px] text-muted -mt-3">{{ __('Generating again for the same days updates the list instead of doubling it; already bought items stay.') }}</p>

            <div class="space-y-4">
                <div class="space-y-2">
                    @foreach ($this->days as $dayData)
                    <label wire:key="generate-day-{{ $dayData['date']->toDateString() }}" class="flex items-center gap-2.5 border-[1.5px] border-field rounded-xl px-3.5 py-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="selectedDaysForShoppingList" value="{{ $dayData['date']->toDateString() }}" class="peer sr-only">
                        <span class="flex items-center justify-center size-5 rounded-[6px] border-2 border-field text-transparent peer-checked:bg-sage peer-checked:border-sage peer-checked:text-white text-xs font-semibold shrink-0 transition-colors">✓</span>
                        <span class="font-inter text-sm font-semibold text-ink">{{ \Illuminate\Support\Str::ucfirst($dayData['date']->copy()->locale(app()->getLocale())->isoFormat('dddd D.MM')) }}</span>
                    </label>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="generateShoppingListFromSelectedDays"
                    wire:target="generateShoppingListFromSelectedDays"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] py-3.5 font-inter text-sm font-semibold disabled:opacity-60">
                    <flux:icon.loading wire:loading wire:target="generateShoppingListFromSelectedDays" variant="mini" />
                    <span wire:loading.remove wire:target="generateShoppingListFromSelectedDays">+ {{ __('Add from selected days') }}</span>
                </button>
            </div>

            <button
                type="button"
                wire:click="generateShoppingListFromFullWeek"
                wire:target="generateShoppingListFromFullWeek"
                wire:loading.attr="disabled"
                class="w-full flex items-center justify-center gap-2 bg-transparent border-[1.5px] border-field text-ink rounded-[14px] py-3.5 font-inter text-sm font-semibold disabled:opacity-60">
                <flux:icon.loading wire:loading wire:target="generateShoppingListFromFullWeek" variant="mini" />
                <span wire:loading.remove wire:target="generateShoppingListFromFullWeek">📅 {{ __('Add from all 7 days') }}</span>
            </button>
        </div>
    </flux:modal>
</div>
