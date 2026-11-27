<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Actions\ShoppingList\GenerateShoppingList;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

new #[Layout('layouts::app')] class extends Component {
    #[Url(as: 'week')]
    public ?string $week = null;

    public bool $showPastDays = false;

    /** Desktop: the day open in the side panel (Y-m-d). */
    #[Url(as: 'day')]
    public ?string $editingDay = null;

    public function openDay(string $date): void
    {
        $this->editingDay = Carbon::parse($date)->toDateString();
    }

    #[On('close-day-panel')]
    public function closeDay(): void
    {
        $this->editingDay = null;
    }

    /** The side panel saved something — re-render the grid. */
    #[On('saved')]
    public function dayChanged(): void
    {
        unset($this->days);
    }

    /** Meal rows of the desktop grid: the four daily ones, dessert only when planned this week. */
    #[Computed]
    public function gridTypes(): array
    {
        $hasDessert = $this->days->contains(fn (array $day) => $day['meals']->contains('type', 'dessert'));

        return array_values(array_filter(Meal::TYPES, fn (string $type) => $type !== 'dessert' || $hasDessert));
    }

    public array $selectedDaysForShoppingList = [];

    /** Health numbers of the shown 7 days, for the "Week review" entry card. */
    #[Computed]
    public function reviewHealth(): array
    {
        return app(\App\Actions\MealPlan\BuildWeekReview::class)->health($this->rangeStart);
    }

    public function render()
    {
        return $this->view()
            ->title(__('Plan'));
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
            ->with(['recipes:id,name,servings', 'recipes.ingredients' => fn ($query) => $query->whereNotNull('ingredients.recipe_id')])
            ->whereBetween(Meal::DATE_COLUMN, [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy(Meal::DATE_COLUMN)
            ->get();

        // Leftover entries point to the cooking entry; its date gives "z soboty".
        $cookedFromIds = $meals->flatMap->recipes->pluck('pivot.cooked_from_id')->filter()->unique();
        $this->cookingDates = $cookedFromIds->isEmpty() ? [] : RecipeMealAssignment::query()
            ->with('meal:id,date')
            ->whereKey($cookedFromIds)
            ->get()
            ->mapWithKeys(fn (RecipeMealAssignment $assignment) => [$assignment->id => $assignment->meal->date])
            ->all() + $this->cookingDates;

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

    /** assignment id => date of the cooking day, for leftover captions. */
    private array $cookingDates = [];

    /** "gotowanie · 6 porcji" / "z soboty · bez zakupów" / "mam gotowe · bez zakupów" under a recipe in the plan. */
    public function recipeCaption(Recipe $recipe): ?string
    {
        if ($recipe->pivot->skip_shopping) {
            return RecipeMealAssignment::readyCaption();
        }

        return match ($recipe->pivot->mode) {
            RecipeMealAssignment::MODE_FULL => implode(' · ', array_filter([__('cooking') . ' · ' . Recipe::servingsLabel((int) $recipe->servings), \App\Support\MealPlan\SemiProducts::caption($recipe)])),
            RecipeMealAssignment::MODE_LEFTOVER => isset($this->cookingDates[$recipe->pivot->cooked_from_id])
                ? Meal::fromDayLabel($this->cookingDates[$recipe->pivot->cooked_from_id]) . ' · ' . __('no shopping')
                : __('leftovers') . ' · ' . __('no shopping'),
            default => \App\Support\MealPlan\SemiProducts::caption($recipe),
        };
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

        $result = app(GenerateShoppingList::class)($dates);

        if ($result['added'] > 0) {
            \App\Support\Push\PushNotifier::toOthers(auth()->user(), __('Shopping list'),
                trans_choice(':name: added :count product from the plan to the shopping list|:name: added :count products from the plan to the shopping list', $result['added'], ['name' => auth()->user()->name]),
                route('shopping-list.index'));
        }

        $this->selectedDaysForShoppingList = [];

        $this->redirectRoute('shopping-list.index', navigate: true);
    }
};
?>

<div data-refresh-on-restore data-pull-refresh>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
            <h1 data-large-title class="font-fraunces text-[28px] lg:text-[32px] font-semibold leading-none tracking-[-0.01em] text-ink">{{ __('Plan') }}</h1>

            <div class="order-last w-full lg:order-none lg:w-auto flex items-center gap-2">
                <div class="flex-1 lg:flex-none lg:w-[230px] flex h-11 items-center justify-between rounded-[14px] bg-sand">
                    <button type="button" wire:click="goToPreviousWeek" class="flex size-11 items-center justify-center text-ink" aria-label="{{ __('Previous week') }}">
                        <x-icon.chevron-left class="size-[18px]" />
                    </button>
                    <span class="text-[15px] font-semibold text-ink">
                        @if ($this->rangeStart->isSameMonth($this->rangeEnd))
                        {{ $this->rangeStart->format('j') }} – {{ $this->rangeEnd->copy()->locale(app()->getLocale())->isoFormat('D MMMM') }}
                        @else
                        {{ $this->rangeStart->copy()->locale(app()->getLocale())->isoFormat('D MMM') }} – {{ $this->rangeEnd->copy()->locale(app()->getLocale())->isoFormat('D MMM') }}
                        @endif
                    </span>
                    <button type="button" wire:click="goToNextWeek" class="flex size-11 items-center justify-center text-ink" aria-label="{{ __('Next week') }}">
                        <x-icon.chevron-right class="size-[18px]" />
                    </button>
                </div>

                <button type="button" wire:click="goToToday" class="h-11 shrink-0 rounded-[14px] border border-field bg-surface px-3.5 text-[14px] font-semibold text-ink">
                    {{ __('Today') }}
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('meals.review', $week ? ['week' => $week] : []) }}" wire:navigate class="hidden lg:inline-flex h-10 items-center px-3 text-[14px] font-semibold text-ink">
                    {{ __('Week review') }}
                </a>
                <a href="{{ route('ai-report.edit') }}" wire:navigate class="hidden lg:inline-flex h-10 items-center gap-1.5 px-3 text-[14px] font-semibold text-ink">
                    <x-icon.sparkle class="size-4" />
                    {{ __('AI plan') }}
                </a>
                <flux:modal.trigger name="generate-shopping-list-modal">
                    <x-ui.pill variant="outline" icon="cart">{{ __('Shopping list') }}</x-ui.pill>
                </flux:modal.trigger>
            </div>
        </div>

        {{-- Desktop: week grid (7 days × meals), day editor in a side panel. --}}
        <div class="hidden lg:block">
            @include('pages.meal.partials.week-grid')
        </div>

        <div class="space-y-3 lg:hidden">
        @php $reviewHealth = $this->reviewHealth; @endphp
        <a href="{{ route('meals.review', $week ? ['week' => $week] : []) }}" wire:navigate
            class="flex min-h-[56px] items-center gap-3 rounded-[20px] border border-line bg-surface px-3.5 py-2.5">
            <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                <span class="text-[15px] font-semibold text-ink">{{ __('Week review') }}</span>
                <span class="text-[13px] text-muted">
                    @if ($reviewHealth['rated'] > 0)
                    {{ __('Healthy :percent% · goal :goal%', ['percent' => $reviewHealth['healthyPercent'], 'goal' => $reviewHealth['minPercent']]) }}
                    @else
                    {{ __('Health and variety of the plan') }}
                    @endif
                </span>
            </span>
            <x-ui.chevron-right />
        </a>
        @if ($this->isCurrentRange)
        <div class="flex justify-center">
            <button type="button" wire:click="togglePastDays" class="inline-flex h-9 items-center gap-1 px-3 text-[13px] font-medium text-muted">
                @if ($showPastDays)
                <x-icon.chevron-down class="size-3.5" />
                {{ __('Hide past days') }}
                @else
                <x-icon.chevron-up class="size-3.5" />
                {{ __('Show past days') }}
                @endif
            </button>
        </div>
        @endif

        <div class="space-y-2.5">
        @foreach ($this->visibleDays as $dayData)
        @php
            $dateString = $dayData['date']->toDateString();
            $dayName = \Illuminate\Support\Str::ucfirst($dayData['date']->copy()->locale(app()->getLocale())->isoFormat('dddd'));
            $isEmpty = $dayData['meals']->isEmpty();
        @endphp
        <div
            wire:key="day-{{ $dateString }}"
            @class([
                'bg-surface rounded-[20px] border overflow-hidden',
                'border-today-border' => $dayData['isToday'],
                'border-line' => ! $dayData['isToday'],
                'opacity-60' => $dayData['isPast'],
            ])>
            <a href="{{ route('meals.day.edit', $dateString) }}" wire:navigate
                aria-label="{{ ($isEmpty ? __('Plan the day') : __('Edit day')) . ': ' . $dayName . ' ' . $dayData['date']->format('j') }}"
                class="flex items-center gap-2 min-h-[52px] px-3.5 py-2">
                <span class="w-7 font-fraunces text-[22px] font-semibold leading-none text-ink tabular-nums">{{ $dayData['date']->format('j') }}</span>
                <span class="text-[14px] font-semibold text-ink-soft">{{ $dayName }}</span>
                @if ($dayData['isToday'])
                <span class="rounded-full bg-today px-2 py-0.5 text-[11px] font-semibold text-today-ink">{{ __('Today') }}</span>
                @endif
                <span class="flex-1"></span>
                @if ($isEmpty)
                <span class="inline-flex items-center gap-1 text-[14px] font-semibold text-terracotta">
                    <x-icon.plus class="size-4" />
                    {{ __('Plan it') }}
                </span>
                @else
                <x-icon.pencil class="size-4 text-muted" />
                @endif
            </a>

            @foreach ($dayData['meals'] as $meal)
            <div wire:key="meal-{{ $meal->id }}" class="flex gap-2.5 px-3.5 border-t border-divider">
                <span class="w-[72px] shrink-0 py-2.5 text-[13px] leading-[1.4] text-muted">{{ Meal::typeLabel($meal->type) }}</span>
                <div class="flex-1 min-w-0 flex flex-col">
                    @foreach ($meal->recipes as $recipe)
                    @php $caption = $this->recipeCaption($recipe); @endphp
                    <button
                        type="button"
                        wire:key="meal-{{ $meal->id }}-recipe-{{ $recipe->id }}"
                        x-data
                        x-on:click="window.openRecipe('{{ route('recipes.show', $recipe) }}')"
                        class="flex items-center gap-1.5 min-h-10 py-2 text-left text-[14px] leading-[1.4] text-ink">
                        <span class="flex-1 min-w-0 flex flex-col">
                            <span>{{ $recipe->name }}</span>
                            @if ($caption)
                            <span class="text-[12px] text-muted">{{ $caption }}</span>
                            @endif
                        </span>
                        <x-ui.chevron-right />
                    </button>
                    @endforeach

                    @if (filled($meal->note))
                    <span class="py-2.5 text-[14px] leading-[1.4] text-ink-soft">{{ $meal->note }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endforeach
        </div>
        </div>
    </div>

    @if ($editingDay)
    {{-- Desktop side panel with the day editor (the phone opens the day editor page instead). --}}
    <div class="hidden lg:block" x-data x-on:keydown.escape.window="$wire.closeDay()">
        <aside role="dialog" aria-modal="true" aria-label="{{ __('Day editor') }}" class="fixed inset-y-0 right-0 z-50 flex w-[400px] flex-col border-l border-line bg-surface shadow-[-12px_0_40px_rgba(31,27,22,0.12)]">
            <div class="flex-1 overflow-y-auto px-5 pt-6 pb-4">
                <livewire:pages::meal.day-edit :date="$editingDay" :in-panel="true" :key="'day-panel-'.$editingDay" />
            </div>
            <p class="border-t border-line px-5 py-3.5 text-[13px] text-muted">{{ __('Changes are saved automatically.') }}</p>
        </aside>
    </div>
    @endif

    <flux:modal name="generate-shopping-list-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-surface! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ __('Generate shopping list') }}</h2>
                <flux:modal.close>
                    <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
                </flux:modal.close>
            </div>

            <p class="text-[13px] text-muted">{{ __('Select the days to collect ingredients from assigned recipes.') }} {{ __('Generating again for the same days updates the list instead of doubling it; already bought items stay.') }}</p>

            <div class="rounded-2xl border border-line divide-y divide-divider">
                @foreach ($this->days as $dayData)
                <label wire:key="generate-day-{{ $dayData['date']->toDateString() }}" class="flex items-center gap-3 min-h-12 px-3.5 cursor-pointer">
                    <input type="checkbox" wire:model="selectedDaysForShoppingList" value="{{ $dayData['date']->toDateString() }}" class="peer sr-only">
                    <span class="flex size-[22px] items-center justify-center rounded-[7px] border-[1.5px] border-field text-transparent peer-checked:bg-sage peer-checked:border-sage peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-ink shrink-0 transition-colors">
                        <x-icon.check class="size-3.5" />
                    </span>
                    <span class="text-[15px] text-ink">{{ \Illuminate\Support\Str::ucfirst($dayData['date']->copy()->locale(app()->getLocale())->isoFormat('dddd D.MM')) }}</span>
                </label>
                @endforeach
            </div>

            <x-ui.button wire:click="generateShoppingListFromSelectedDays" wire:target="generateShoppingListFromSelectedDays" wire:loading.attr="disabled">
                {{ __('Add from selected days') }}
            </x-ui.button>
            <x-ui.button variant="outline" wire:click="generateShoppingListFromFullWeek" wire:target="generateShoppingListFromFullWeek" wire:loading.attr="disabled">
                {{ __('Add from all 7 days') }}
            </x-ui.button>
        </div>
    </flux:modal>
</div>
