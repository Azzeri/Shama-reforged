<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    public string $date;

    public array $meals = [];

    public ?string $pickingType = null;

    public ?int $pickingRecipeIndex = null;

    public string $recipeSearch = '';

    public ?int $previewingRecipeId = null;

    public ?string $repeatingKey = null;

    public string $repeatDayOffset = '';

    public string $repeatMealType = '';

    public function mount(string $date): void
    {
        $this->date = $date;

        $draft = session($this->draftSessionKey());

        if (is_array($draft) && empty(array_diff(Meal::TYPES, array_keys($draft)))) {
            $this->meals = $draft;

            return;
        }

        $existingByType = Meal::query()
            ->with('recipes:id')
            ->whereDate(Meal::DATE_COLUMN, $this->day->toDateString())
            ->orderBy('id')
            ->get()
            ->groupBy(Meal::TYPE_COLUMN);

        $this->meals = collect(Meal::TYPES)
            ->mapWithKeys(function (string $type) use ($existingByType) {
                $mealsOfType = $existingByType->get($type, collect());
                $primary = $mealsOfType->first();

                $recipeIds = $mealsOfType
                    ->flatMap(fn (Meal $meal) => $meal->recipes->pluck('id'))
                    ->unique()
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                return [$type => [
                    'id' => $primary?->id,
                    'extraIds' => $mealsOfType->skip(1)->pluck('id')->all(),
                    'recipeIds' => $recipeIds ?: [''],
                    'note' => $primary?->note ?? '',
                ]];
            })
            ->all();
    }

    /**
     * Unsaved edits are drafted into the session so that navigating away to
     * view a recipe (a real page, not an overlay) and back doesn't lose them.
     */
    private function draftSessionKey(): string
    {
        return "day-edit-draft.{$this->date}";
    }

    private function saveDraft(): void
    {
        session([$this->draftSessionKey() => $this->meals]);
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'meals')) {
            $this->saveDraft();
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

    /**
     * Day options for the "repeat this recipe" panel, offset (0-6) from the
     * start of the week the currently-edited day falls in — so "repeat to
     * Wednesday" always means Wednesday of this same week, regardless of
     * which day is currently being edited.
     */
    #[Computed]
    public function weekDayOptions(): Collection
    {
        $weekStart = $this->day->copy()->startOfWeek();

        return collect(range(0, 6))->map(fn (int $offset) => [
            'offset' => $offset,
            'label' => $weekStart->copy()->addDays($offset)->locale('en')->isoFormat('dddd, D MMM'),
        ]);
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

    #[Computed]
    public function pickerRecipes(): Collection
    {
        if ($this->pickingType === null) {
            return collect();
        }

        $type = $this->pickingType;

        return Recipe::query()
            ->when($this->recipeSearch, fn ($query) => $query->where('name', 'like', '%' . $this->recipeSearch . '%'))
            ->when($type, function ($query) use ($type) {
                $query->whereHas('tags', function ($tagQuery) use ($type) {
                    $tagQuery->where('category', Tag::MEAL_TYPE)
                        ->where('name', Meal::typeLabel($type));
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function openRecipePicker(string $type, int $recipeIndex): void
    {
        $this->pickingType = $type;
        $this->pickingRecipeIndex = $recipeIndex;
        $this->recipeSearch = '';
    }

    public function closeRecipePicker(): void
    {
        $this->pickingType = null;
        $this->pickingRecipeIndex = null;
        $this->recipeSearch = '';
    }

    public function selectRecipeForPicker(int $recipeId): void
    {
        if ($this->pickingType !== null && $this->pickingRecipeIndex !== null) {
            $this->meals[$this->pickingType]['recipeIds'][$this->pickingRecipeIndex] = (string) $recipeId;
        }

        $this->closeRecipePicker();
        $this->saveDraft();
    }

    public function showRecipePreview(int $recipeId): void
    {
        $this->previewingRecipeId = $recipeId;
    }

    public function closeRecipePreview(): void
    {
        $this->previewingRecipeId = null;
    }

    public function addRecipeRow(string $type): void
    {
        $this->meals[$type]['recipeIds'][] = '';
        $this->saveDraft();
    }

    public function removeRecipeRow(string $type, int $recipeIndex): void
    {
        unset($this->meals[$type]['recipeIds'][$recipeIndex]);
        $this->meals[$type]['recipeIds'] = array_values($this->meals[$type]['recipeIds']) ?: [''];
        $this->saveDraft();
    }

    public function openRepeatPanel(string $type, int $recipeIndex): void
    {
        $this->repeatingKey = "{$type}:{$recipeIndex}";
        $this->repeatDayOffset = '';
        $this->repeatMealType = '';
        $this->resetErrorBag();
    }

    public function closeRepeatPanel(): void
    {
        $this->repeatingKey = null;
    }

    /**
     * Attaches the recipe to another (day, meal type) slot, independent of
     * the current day's draft/save flow, so shared ingredients merge on the
     * shopping list once both days are generated (matched by ingredient +
     * unit, not by any explicit link between the two Meal rows).
     */
    public function repeatRecipe(string $type, int $recipeIndex): void
    {
        $this->validate([
            'repeatDayOffset' => ['required', Rule::in(range(0, 6))],
            'repeatMealType' => ['required', Rule::in(Meal::TYPES)],
        ]);

        $recipeId = (int) ($this->meals[$type]['recipeIds'][$recipeIndex] ?? 0);

        $targetDate = $this->day->copy()->startOfWeek()->addDays((int) $this->repeatDayOffset);

        $targetMeal = Meal::query()
            ->whereDate(Meal::DATE_COLUMN, $targetDate->toDateString())
            ->where(Meal::TYPE_COLUMN, $this->repeatMealType)
            ->first();

        if (! $targetMeal) {
            $targetMeal = Meal::create([
                'date' => $targetDate->copy()->setTime(12, 0),
                'type' => $this->repeatMealType,
            ]);
        }

        $targetMeal->recipes()->syncWithoutDetaching([$recipeId]);

        $this->closeRepeatPanel();

        Flux::toast(variant: 'success', text: __('Recipe added to the selected day.'));
    }

    public function cancel(): void
    {
        session()->forget($this->draftSessionKey());

        $this->redirectRoute('meals.day', $this->date, navigate: true);
    }

    public function save(): void
    {
        foreach ($this->meals as $type => $row) {
            $this->meals[$type]['recipeIds'] = array_values(array_filter(
                $row['recipeIds'],
                fn ($recipeId) => $recipeId !== '',
            ));
        }

        $this->validate([
            'meals.*.note' => ['nullable', 'string', 'max:255'],
            'meals.*.recipeIds' => ['array'],
            'meals.*.recipeIds.*' => ['integer', 'exists:recipes,id'],
        ]);

        foreach ($this->meals as $type => $row) {
            if (count($row['recipeIds']) !== count(array_unique($row['recipeIds']))) {
                $this->addError("meals.$type.recipeIds", __('This meal already has that recipe added.'));
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $day = $this->day;

        foreach ($this->meals as $type => $row) {
            $hasContent = ! empty($row['recipeIds']) || trim($row['note']) !== '';

            if (! $hasContent) {
                if ($row['id']) {
                    Meal::query()->whereKey($row['id'])->delete();
                }

                if (! empty($row['extraIds'])) {
                    Meal::query()->whereKey($row['extraIds'])->delete();
                }

                continue;
            }

            $meal = $row['id']
                ? Meal::query()->findOrFail($row['id'])
                : new Meal(['date' => $day->copy()->setTime(12, 0), 'type' => $type]);

            $meal->type = $type;
            $meal->note = trim($row['note']) !== '' ? trim($row['note']) : null;
            $meal->save();
            $meal->recipes()->sync($row['recipeIds']);

            if (! empty($row['extraIds'])) {
                Meal::query()->whereKey($row['extraIds'])->delete();
            }
        }

        session()->forget($this->draftSessionKey());

        Flux::toast(variant: 'success', text: __('Day saved.'));

        $this->redirectRoute('meals.day', $day->toDateString(), navigate: true);
    }
};
?>

<div>
    <div class="space-y-5">
        <x-ui.back-button wire:click="cancel" />

        <h1 class="font-fraunces text-2xl font-semibold text-ink">{{ __('Edit day') }}: {{ $this->day->locale('en')->isoFormat('dddd, D MMMM') }}</h1>

        <form wire:submit="save" class="space-y-4">
            @foreach (\App\Models\Meal::TYPES as $type)
            @php $row = $meals[$type]; @endphp
            <div wire:key="meal-type-{{ $type }}" class="bg-white rounded-[18px] p-4 border border-ink/10 space-y-3">
                <x-ui.eyebrow>{{ Meal::typeLabel($type) }}</x-ui.eyebrow>

                <div>
                    <div class="space-y-2.5">
                        @foreach ($row['recipeIds'] as $recipeIndex => $recipeId)
                        <div wire:key="meal-{{ $type }}-recipe-{{ $recipeIndex }}">
                            @if ($recipeId && $this->recipesById->has((int) $recipeId))
                            @php $selectedRecipe = $this->recipesById[(int) $recipeId]; @endphp
                            <div class="rounded-xl border border-ink/10 overflow-hidden">
                                <a
                                    href="{{ route('recipes.show', $selectedRecipe) }}?back={{ urlencode(route('meals.day.edit', $date, false)) }}"
                                    wire:navigate
                                    class="block px-3.5 py-2.5 hover:bg-sand/40 transition-colors">
                                    <div class="font-manrope text-sm font-bold text-ink mb-1.5">{{ $selectedRecipe->name }}</div>
                                    <x-recipe.nutrition :recipe="$selectedRecipe" compact />
                                </a>
                                <div class="flex border-t border-ink/10">
                                    <button
                                        type="button"
                                        wire:click="openRecipePicker('{{ $type }}', {{ $recipeIndex }})"
                                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 font-manrope text-[12.5px] font-bold text-terracotta-dark hover:bg-sand/40 border-e border-ink/10">
                                        <flux:icon.arrows-right-left class="size-3.5" />
                                        {{ __('Swap') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeRecipeRow('{{ $type }}', {{ $recipeIndex }})"
                                        class="w-12 flex items-center justify-center text-terracotta-dark hover:bg-sand/40">
                                        <flux:icon.trash class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="openRepeatPanel('{{ $type }}', {{ $recipeIndex }})"
                                class="flex items-center gap-1.5 mt-2 font-manrope text-[12.5px] font-bold text-forest">
                                <flux:icon.arrow-path class="size-3.5" />
                                {{ __('Repeat on another day') }}
                            </button>

                            @if ($repeatingKey === "{$type}:{$recipeIndex}")
                            <div class="mt-2 rounded-xl border border-ink/10 bg-sand/40 p-3.5 space-y-3">
                                <x-ui.eyebrow>{{ __('Repeat this recipe') }}</x-ui.eyebrow>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <select
                                        wire:model="repeatDayOffset"
                                        class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                                        <option value="">{{ __('Day') }}</option>
                                        @foreach ($this->weekDayOptions as $option)
                                        <option value="{{ $option['offset'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>

                                    <select
                                        wire:model="repeatMealType"
                                        class="w-full border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30">
                                        <option value="">{{ __('Meal type') }}</option>
                                        @foreach (\App\Models\Meal::TYPES as $optionType)
                                        <option value="{{ $optionType }}">{{ Meal::typeLabel($optionType) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <x-ui.field-error name="repeatDayOffset" />
                                <x-ui.field-error name="repeatMealType" />

                                <p class="font-manrope text-xs text-ink/50">{{ __('Ingredients will sum up on the shopping list.') }}</p>

                                <div class="flex items-center justify-end gap-3">
                                    <button type="button" wire:click="closeRepeatPanel" class="font-manrope text-sm font-extrabold text-forest px-3 py-2">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="repeatRecipe('{{ $type }}', {{ $recipeIndex }})"
                                        class="bg-forest hover:bg-forest/90 transition-colors text-white rounded-xl px-4 py-2 font-manrope text-sm font-extrabold">
                                        {{ __('Add') }}
                                    </button>
                                </div>
                            </div>
                            @endif
                            @else
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    wire:click="openRecipePicker('{{ $type }}', {{ $recipeIndex }})"
                                    class="flex-1 flex items-center justify-between gap-2 border-[1.5px] border-ink/25 bg-white rounded-2xl px-3.5 py-[13px] font-manrope text-base sm:text-sm text-left text-ink/35">
                                    <span class="truncate">{{ __('Select recipe') }}</span>
                                    <flux:icon.chevron-down class="size-4 text-ink/40 shrink-0" />
                                </button>

                                <button
                                    type="button"
                                    wire:click="removeRecipeRow('{{ $type }}', {{ $recipeIndex }})"
                                    class="shrink-0 w-10 h-11 flex items-center justify-center rounded-xl bg-terracotta text-white">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    <x-ui.field-error name="meals.{{ $type }}.recipeIds" />

                    <button
                        type="button"
                        wire:click="addRecipeRow('{{ $type }}')"
                        class="flex items-center gap-2 text-terracotta font-manrope text-[13.5px] font-bold py-2">
                        + {{ __('Add recipe') }}
                    </button>
                </div>

                <div>
                    <x-ui.eyebrow optional>{{ __('Notatka') }}</x-ui.eyebrow>
                    <x-ui.text-input
                        wire:model="meals.{{ $type }}.note"
                        placeholder="{{ __('np. Jemy na mieście') }}"
                        class="w-full" />
                    <p class="font-manrope text-xs text-ink/50 mt-1.5">
                        {{ __('Użyj zamiast przepisu, gdy nie planujesz nic konkretnego — nie trafi na listę zakupów.') }}
                    </p>
                    <x-ui.field-error name="meals.{{ $type }}.note" />
                </div>
            </div>
            @endforeach

            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="cancel" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    {{ __('Save day') }}
                </button>
            </div>
        </form>
    </div>

    @if ($pickingType !== null)
    <div class="fixed inset-0 z-50 bg-cream overflow-y-auto">
        <div class="max-w-xl mx-auto px-4 py-6 pb-8">
            <button
                type="button"
                wire:click="closeRecipePicker"
                class="inline-flex items-center justify-center size-10 rounded-2xl bg-white shadow-[0_1px_2px_rgba(43,33,24,0.06),0_6px_14px_rgba(43,33,24,0.07)] text-ink mb-5">
                <flux:icon.arrow-left class="size-4" />
            </button>

            <h1 class="font-fraunces text-[28px] font-semibold text-ink mb-5">{{ __('Select recipe') }}</h1>

            <x-ui.text-input
                wire:model.live="recipeSearch"
                placeholder="{{ __('Search by name') }}"
                class="w-full mb-5"
                autofocus />

            <div class="rounded-2xl border border-ink/10 overflow-hidden bg-white">
                @forelse ($this->pickerRecipes as $pickerRecipe)
                <button
                    type="button"
                    wire:key="picker-recipe-{{ $pickerRecipe->id }}"
                    x-data="{
                        pressTimer: null,
                        longPressed: false,
                        startPress() {
                            this.longPressed = false;
                            this.pressTimer = setTimeout(() => {
                                this.longPressed = true;
                                $wire.showRecipePreview({{ $pickerRecipe->id }});
                            }, 500);
                        },
                        endPress() {
                            clearTimeout(this.pressTimer);
                        },
                        handleClick() {
                            if (this.longPressed) {
                                this.longPressed = false;
                                return;
                            }
                            $wire.selectRecipeForPicker({{ $pickerRecipe->id }});
                        },
                    }"
                    @mousedown="startPress"
                    @mouseup="endPress"
                    @mouseleave="endPress"
                    @touchstart="startPress"
                    @touchend="endPress"
                    @click="handleClick"
                    @contextmenu.prevent
                    class="w-full text-left select-none px-4 py-3.5 font-manrope text-[14.5px] font-semibold text-ink border-b border-ink/10 last:border-b-0 hover:bg-sand/40">
                    {{ $pickerRecipe->name }}
                </button>
                @empty
                <div class="px-4 py-8 text-center font-manrope text-sm text-ink/50">
                    {{ __('No recipes found.') }}
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if ($previewingRecipeId !== null)
    <div
        class="fixed inset-0 z-[60] bg-[rgba(27,21,16,0.45)] flex items-end justify-center"
        wire:click.self="closeRecipePreview">
        <div class="w-full max-w-xl rounded-t-[24px] bg-cream max-h-[88dvh] overflow-y-auto shadow-[0_-10px_40px_rgba(0,0,0,0.3)] p-5">
            <div class="flex justify-end -mt-1 -me-1 mb-1">
                <button
                    type="button"
                    wire:click="closeRecipePreview"
                    class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
            </div>

            <livewire:pages::recipe.show :recipe="$this->recipesById[$previewingRecipeId]" :show-back-button="false" :key="'recipe-preview-'.$previewingRecipeId" />
        </div>
    </div>
    @endif
</div>
