<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShoppingListController extends Controller
{
    public function index(): View
    {
        $shoppingList = $this->mainList();

        $items = $shoppingList->items()
            ->orderBy(ShoppingListItem::IS_CHECKED_COLUMN)
            ->latest()
            ->get();

        $activeItems = $items->where(ShoppingListItem::IS_CHECKED_COLUMN, false);
        $checkedItems = $items->where(ShoppingListItem::IS_CHECKED_COLUMN, true);

        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();
        $currentWeekDays = collect(range(0, 6))
            ->map(fn (int $i) => strtolower($weekStart->copy()->addDays($i)->englishDayOfWeek))
            ->all();

        $checkedThisWeek = $checkedItems->filter(function (ShoppingListItem $item) use ($currentWeekDays) {
            return in_array($item->{ShoppingListItem::WEEK_DAY_COLUMN}, $currentWeekDays, true);
        });

        $checkedUnscheduledThisWeek = $checkedItems
            ->whereNull(ShoppingListItem::WEEK_DAY_COLUMN)
            ->filter(fn (ShoppingListItem $item) => $item->created_at?->betweenIncluded($weekStart, $weekEnd))
            ->values();

        return view('shopping-list.index', [
            'shoppingList' => $shoppingList,
            'activeItemsByDay' => $this->groupByDay($activeItems->whereNotNull(ShoppingListItem::WEEK_DAY_COLUMN)),
            'checkedItemsByDay' => $this->groupByDay($checkedThisWeek->whereNotNull(ShoppingListItem::WEEK_DAY_COLUMN)),
            'activeUnscheduledItems' => $activeItems->whereNull(ShoppingListItem::WEEK_DAY_COLUMN)->values(),
            'checkedUnscheduledItems' => $checkedUnscheduledThisWeek,
            'weekDayLabels' => ShoppingListItem::WEEK_DAY_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ShoppingListItem::NAME_COLUMN => ['required', 'string', 'max:255'],
            ShoppingListItem::QUANTITY_COLUMN => ['nullable', 'string', 'max:255'],
            ShoppingListItem::WEEK_DAY_COLUMN => ['nullable', 'string', Rule::in(ShoppingListItem::WEEK_DAYS)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->mainList()->items()->create([
            ShoppingListItem::NAME_COLUMN => $validated[ShoppingListItem::NAME_COLUMN],
            ShoppingListItem::QUANTITY_COLUMN => $validated[ShoppingListItem::QUANTITY_COLUMN] ?? null,
            ShoppingListItem::WEEK_DAY_COLUMN => $validated[ShoppingListItem::WEEK_DAY_COLUMN] ?? null,
            ShoppingListItem::IS_CHECKED_COLUMN => false,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('shopping-list.index')->with('status', 'Dodano pozycję do listy zakupów.');
    }

    public function toggle(ShoppingListItem $shoppingListItem): RedirectResponse
    {
        $shoppingListItem->update([
            ShoppingListItem::IS_CHECKED_COLUMN => ! $shoppingListItem->{ShoppingListItem::IS_CHECKED_COLUMN},
        ]);

        return redirect()->route('shopping-list.index');
    }

    public function clearUnchecked(): RedirectResponse
    {
        $deleted = $this->mainList()->items()
            ->where(ShoppingListItem::IS_CHECKED_COLUMN, false)
            ->delete();

        return redirect()
            ->route('shopping-list.index')
            ->with('status', "Usunięto {$deleted} niekupionych pozycji.");
    }

    public function generate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'mode' => ['required', 'string', Rule::in(['selected-days', 'full-week'])],
            'days' => ['nullable', 'array'],
            'days.*' => ['date'],
        ]);

        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek();
        $dates = $this->resolveDatesToImport($validated, $weekStart);

        if ($dates->isEmpty()) {
            return redirect()
                ->route('meals.index', ['week' => $weekStart->toDateString()])
                ->with('status', 'Nie wybrano żadnych dni do importu.');
        }

        $meals = Meal::query()
            ->with(['recipes' => fn ($query) => $query->with('ingredients:id,name')])
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate(Meal::DATE_COLUMN, $date->toDateString());
                }
            })
            ->get();

        $created = 0;
        $shoppingList = $this->mainList();

        foreach ($meals as $meal) {
            foreach ($meal->recipes as $recipe) {
                foreach ($recipe->ingredients as $ingredient) {
                    $quantity = trim((string) ($ingredient->pivot?->quantity ?? ''));

                    if ($quantity === '') {
                        continue;
                    }

                    $shoppingList->items()->create([
                        ShoppingListItem::NAME_COLUMN => $ingredient->name,
                        ShoppingListItem::QUANTITY_COLUMN => $quantity,
                        ShoppingListItem::IS_CHECKED_COLUMN => false,
                        ShoppingListItem::WEEK_DAY_COLUMN => strtolower($meal->date->englishDayOfWeek),
                        'notes' => 'Przepis: '.$recipe->name,
                        'recipe_id' => $recipe->id,
                        'meal_id' => $meal->id,
                    ]);

                    $created++;
                }
            }
        }

        return redirect()
            ->route('shopping-list.index')
            ->with('status', "Dodano {$created} pozycji do listy zakupów.");
    }

    private function mainList(): ShoppingList
    {
        return ShoppingList::query()->firstOrCreate([
            'id' => 1,
        ], [
            'name' => 'Główna lista zakupów',
        ]);
    }

    /**
     * @param  array{mode: string, days?: array<int, string>}  $validated
     * @return Collection<int, Carbon>
     */
    private function resolveDatesToImport(array $validated, Carbon $weekStart): Collection
    {
        if ($validated['mode'] === 'full-week') {
            return collect(range(0, 6))->map(fn (int $day) => $weekStart->copy()->addDays($day));
        }

        return collect($validated['days'] ?? [])
            ->map(fn (string $date) => Carbon::parse($date)->startOfDay())
            ->filter(fn (Carbon $date) => $date->betweenIncluded($weekStart, $weekStart->copy()->endOfWeek()))
            ->values();
    }

    /**
     * @param  Collection<int, ShoppingListItem>  $items
     * @return array<string, Collection<int, ShoppingListItem>>
     */
    private function groupByDay(Collection $items): array
    {
        $grouped = [];

        foreach (ShoppingListItem::WEEK_DAYS as $weekDay) {
            $grouped[$weekDay] = $items
                ->where(ShoppingListItem::WEEK_DAY_COLUMN, $weekDay)
                ->values();
        }

        return $grouped;
    }
}