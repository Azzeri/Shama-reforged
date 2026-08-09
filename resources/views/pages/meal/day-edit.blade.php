<?php

use App\Models\Meal;
use App\Models\Recipe;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::app')] class extends Component {
    public string $date;

    public array $meals = [];

    public function mount(string $date): void
    {
        $this->date = $date;

        $this->meals = Meal::query()
            ->with('recipes:id')
            ->whereDate(Meal::DATE_COLUMN, $this->day->toDateString())
            ->orderBy(Meal::DATE_COLUMN)
            ->get()
            ->map(fn (Meal $meal) => [
                'id' => $meal->id,
                'type' => $meal->type,
                'recipeIds' => $meal->recipes->pluck('id')->map(fn ($id) => (string) $id)->all() ?: [''],
                'note' => $meal->note ?? '',
            ])
            ->values()
            ->all();

        if (empty($this->meals)) {
            $this->meals = [$this->emptyMealRow()];
        }
    }

    public function render()
    {
        return $this->view()
            ->title(__('Edit day') . ': ' . $this->day->locale('en')->isoFormat('dddd, D MMMM'));
    }

    #[Computed]
    public function day(): CarbonInterface
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    #[Computed]
    public function recipeOptions(): Collection
    {
        return Recipe::query()->orderBy('name')->get();
    }

    #[Computed]
    public function recipesById(): Collection
    {
        return $this->recipeOptions->keyBy('id');
    }

    private function emptyMealRow(): array
    {
        return ['id' => null, 'type' => '', 'recipeIds' => [''], 'note' => ''];
    }

    public function addMeal(): void
    {
        $this->meals[] = $this->emptyMealRow();
    }

    public function removeMeal(int $mealIndex): void
    {
        if (($this->meals[$mealIndex]['id'] ?? null) !== null) {
            return;
        }

        unset($this->meals[$mealIndex]);
        $this->meals = array_values($this->meals);
    }

    public function addRecipeRow(int $mealIndex): void
    {
        $this->meals[$mealIndex]['recipeIds'][] = '';
    }

    public function removeRecipeRow(int $mealIndex, int $recipeIndex): void
    {
        unset($this->meals[$mealIndex]['recipeIds'][$recipeIndex]);
        $this->meals[$mealIndex]['recipeIds'] = array_values($this->meals[$mealIndex]['recipeIds']) ?: [''];
    }

    public function save(): void
    {
        foreach ($this->meals as $index => $row) {
            $this->meals[$index]['recipeIds'] = array_values(array_filter(
                $row['recipeIds'],
                fn ($recipeId) => $recipeId !== '',
            ));
        }

        $this->validate([
            'meals.*.type' => ['required', 'string', Rule::in(Meal::TYPES)],
            'meals.*.note' => ['nullable', 'string', 'max:255'],
            'meals.*.recipeIds' => ['array'],
            'meals.*.recipeIds.*' => ['integer', 'distinct', 'exists:recipes,id'],
        ]);

        foreach ($this->meals as $index => $row) {
            if (empty($row['recipeIds']) && trim($row['note']) === '') {
                $this->addError("meals.$index.note", __('Wybierz przynajmniej jeden przepis albo dodaj notatkę.'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $day = $this->day;

        foreach ($this->meals as $row) {
            $meal = $row['id']
                ? Meal::query()->findOrFail($row['id'])
                : new Meal(['date' => $day->copy()->setTime(12, 0)]);

            $meal->type = $row['type'];
            $meal->note = trim($row['note']) !== '' ? trim($row['note']) : null;
            $meal->save();
            $meal->recipes()->sync($row['recipeIds']);
        }

        $this->redirectRoute('meals.day', $day->toDateString(), navigate: true);
    }
};
?>

<div>
    <div class="space-y-5">
        <x-ui.back-button href="{{ route('meals.day', $date) }}" />

        <h1 class="font-fraunces text-2xl font-semibold text-ink">{{ __('Edit day') }}: {{ $this->day->locale('en')->isoFormat('dddd, D MMMM') }}</h1>

        <form wire:submit="save" class="space-y-4">
            @foreach ($meals as $mealIndex => $row)
            <div wire:key="meal-row-{{ $mealIndex }}" class="bg-white rounded-[18px] p-4 border border-ink/10 space-y-3">
                @if (is_null($row['id']))
                <div class="flex justify-end -mb-1">
                    <button
                        type="button"
                        wire:click="removeMeal({{ $mealIndex }})"
                        class="text-terracotta-dark p-1">
                        <flux:icon.trash class="size-4" />
                    </button>
                </div>
                @endif

                <div>
                    <x-ui.eyebrow>{{ __('Meal type') }}</x-ui.eyebrow>
                    <select wire:model="meals.{{ $mealIndex }}.type" class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                        <option value="">{{ __('Select type') }}</option>
                        @foreach (\App\Models\Meal::TYPES as $type)
                        <option value="{{ $type }}">{{ Meal::typeLabel($type) }}</option>
                        @endforeach
                    </select>
                    <x-ui.field-error name="meals.{{ $mealIndex }}.type" />
                </div>

                <div>
                    <x-ui.eyebrow>{{ __('Recipes') }}</x-ui.eyebrow>

                    <div class="space-y-2.5">
                        @foreach ($row['recipeIds'] as $recipeIndex => $recipeId)
                        <div wire:key="meal-{{ $mealIndex }}-recipe-{{ $recipeIndex }}" class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <select wire:model.live="meals.{{ $mealIndex }}.recipeIds.{{ $recipeIndex }}" class="flex-1 border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                                    <option value="">{{ __('Select recipe') }}</option>
                                    @foreach ($this->recipeOptions as $recipeOption)
                                    <option value="{{ $recipeOption->id }}">{{ $recipeOption->name }}</option>
                                    @endforeach
                                </select>

                                <button
                                    type="button"
                                    wire:click="removeRecipeRow({{ $mealIndex }}, {{ $recipeIndex }})"
                                    class="shrink-0 w-10 h-11 flex items-center justify-center rounded-xl bg-terracotta text-white">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>

                            @if ($recipeId && $this->recipesById->has((int) $recipeId))
                            @php $selectedRecipe = $this->recipesById[(int) $recipeId]; @endphp
                            <a
                                href="{{ route('recipes.show', $selectedRecipe) }}"
                                wire:navigate
                                class="block border border-ink/10 rounded-xl px-3.5 py-2.5 hover:bg-sand/40 transition-colors">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <span class="font-manrope text-sm font-bold text-ink">{{ $selectedRecipe->name }}</span>
                                    <flux:icon.chevron-right class="size-3.5 text-ink/30 shrink-0" />
                                </div>
                                <x-recipe.nutrition :recipe="$selectedRecipe" compact />
                            </a>
                            @endif
                        </div>
                        <x-ui.field-error name="meals.{{ $mealIndex }}.recipeIds.{{ $recipeIndex }}" />
                        @endforeach
                    </div>

                    <button
                        type="button"
                        wire:click="addRecipeRow({{ $mealIndex }})"
                        class="flex items-center gap-2 text-terracotta font-manrope text-[13.5px] font-bold py-2">
                        + {{ __('Add recipe') }}
                    </button>
                </div>

                <div>
                    <x-ui.eyebrow optional>{{ __('Notatka') }}</x-ui.eyebrow>
                    <x-ui.text-input
                        wire:model="meals.{{ $mealIndex }}.note"
                        placeholder="{{ __('np. Jemy na mieście') }}"
                        class="w-full" />
                    <p class="font-manrope text-xs text-ink/50 mt-1.5">
                        {{ __('Użyj zamiast przepisu, gdy nie planujesz nic konkretnego — nie trafi na listę zakupów.') }}
                    </p>
                    <x-ui.field-error name="meals.{{ $mealIndex }}.note" />
                </div>
            </div>
            @endforeach

            <button
                type="button"
                wire:click="addMeal"
                class="w-full bg-transparent border-[1.5px] border-ink/20 text-ink rounded-xl px-4 py-3 font-manrope text-sm font-extrabold">
                + {{ __('Add meal') }}
            </button>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('meals.day', $date) }}" wire:navigate class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    {{ __('Save day') }}
                </button>
            </div>
        </form>
    </div>
</div>
