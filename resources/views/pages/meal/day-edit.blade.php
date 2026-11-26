<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/*
 * Day editor. Every change (adding/removing a recipe, editing a note) is
 * persisted immediately — there is no Save button.
 *
 * Adding a recipe is two steps: pick it, then (for recipes with more
 * servings than one meal uses) choose "one meal" or "cooking the whole
 * recipe" + the following days that eat the leftovers.
 */
new #[Layout('layouts::app')] class extends Component {
    /** Meal types shown only when they already have content or were added by hand. */
    public const OPTIONAL_TYPES = ['dessert'];

    /** Days after the edited one offered for leftovers. */
    public const LEFTOVER_DAY_CHOICES = 5;

    public string $date;

    /** Rendered inside the desktop calendar's side panel instead of as a page. */
    public bool $inPanel = false;

    /** @var array<string, array{entries: list<array>, note: string}> */
    public array $meals = [];

    public array $shownOptionalTypes = [];

    public ?string $editingNoteType = null;

    public string $noteDraft = '';

    /** Meal type the "add recipe" sheet is open for. */
    public ?string $pickingType = null;

    public string $recipeSearch = '';

    /** Recipe previewed inside the sheet before choosing it. */
    public ?int $previewRecipeId = null;

    /** Step 2 of the sheet: the chosen recipe. */
    public ?int $chosenRecipeId = null;

    public string $chosenMode = RecipeMealAssignment::MODE_SINGLE;

    /** @var list<string> Y-m-d */
    public array $leftoverDates = [];

    public function mount(string $date): void
    {
        $this->date = Carbon::parse($date)->toDateString();
        $this->loadMeals();
    }

    public function render()
    {
        return $this->view()->title($this->title());
    }

    private function title(): string
    {
        return \Illuminate\Support\Str::ucfirst($this->day->copy()->locale(app()->getLocale())->isoFormat('dddd, D MMMM'));
    }

    private function loadMeals(): void
    {
        $existingByType = Meal::query()
            ->with('recipes:id,name,servings')
            ->whereDate(Meal::DATE_COLUMN, $this->date)
            ->orderBy('id')
            ->get()
            ->groupBy(Meal::TYPE_COLUMN);

        $cookedFromIds = $existingByType->flatten()->flatMap->recipes->pluck('pivot.cooked_from_id')->filter()->unique();
        $cookingDates = $cookedFromIds->isEmpty() ? collect() : RecipeMealAssignment::query()
            ->with('meal:id,date')->whereKey($cookedFromIds)->get()
            ->mapWithKeys(fn (RecipeMealAssignment $assignment) => [$assignment->id => $assignment->meal->date]);

        $this->meals = collect(Meal::TYPES)->mapWithKeys(function (string $type) use ($existingByType, $cookingDates) {
            $mealsOfType = $existingByType->get($type, collect());

            return [$type => [
                'entries' => $mealsOfType->flatMap(fn (Meal $meal) => $meal->recipes->map(fn (Recipe $recipe) => [
                    'assignmentId' => $recipe->pivot->id,
                    'recipeId' => $recipe->id,
                    'name' => $recipe->name,
                    'caption' => match ($recipe->pivot->mode) {
                        RecipeMealAssignment::MODE_FULL => __('cooking') . ' · ' . Recipe::servingsLabel((int) $recipe->servings),
                        RecipeMealAssignment::MODE_LEFTOVER => ($cookingDates->has($recipe->pivot->cooked_from_id)
                            ? Meal::fromDayLabel($cookingDates[$recipe->pivot->cooked_from_id])
                            : __('leftovers')) . ' · ' . __('no shopping'),
                        default => null,
                    },
                ]))->values()->all(),
                'note' => $mealsOfType->first()?->note ?? '',
            ]];
        })->all();
    }

    #[Computed]
    public function day(): CarbonInterface
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    #[Computed]
    public function visibleTypes(): array
    {
        return array_values(array_filter(Meal::TYPES, fn (string $type) => ! in_array($type, self::OPTIONAL_TYPES, true)
            || in_array($type, $this->shownOptionalTypes, true)
            || ! empty($this->meals[$type]['entries'])
            || trim($this->meals[$type]['note']) !== ''));
    }

    #[Computed]
    public function hiddenOptionalTypes(): array
    {
        return array_values(array_diff(self::OPTIONAL_TYPES, $this->visibleTypes));
    }

    /** Recently planned recipes (newest first), archived ones left out. */
    #[Computed]
    public function recentRecipes(): Collection
    {
        $ids = RecipeMealAssignment::query()
            ->where('mode', '!=', RecipeMealAssignment::MODE_LEFTOVER)
            ->latest('id')
            ->limit(80)
            ->pluck('recipe_id')
            ->unique()
            ->take(8)
            ->values();

        $recipes = Recipe::query()->active()->whereKey($ids)->get(['id', 'name', 'servings'])->keyBy('id');

        return $ids->map(fn (int $id) => $recipes->get($id))->filter()->values();
    }

    /**
     * Picker list: search results across all active recipes, or (no search)
     * recently used ones followed by the recipes tagged with this meal type.
     *
     * @return list<array{label: string, recipes: Collection}>
     */
    #[Computed]
    public function pickerSections(): array
    {
        if ($this->pickingType === null) {
            return [];
        }

        $search = trim($this->recipeSearch);

        if ($search !== '') {
            return [[
                'label' => __('Results'),
                'recipes' => Recipe::query()->active()
                    ->where('name', 'like', '%' . $search . '%')
                    ->orderBy('name')
                    ->get(['id', 'name', 'servings']),
            ]];
        }

        $recent = $this->recentRecipes;
        $type = $this->pickingType;

        $matching = Recipe::query()->active()
            ->whereNotIn('id', $recent->pluck('id'))
            ->whereHas('tags', fn ($query) => $query->where('category', Tag::MEAL_TYPE)->where('name', Meal::tagName($type)))
            ->orderBy('name')
            ->get(['id', 'name', 'servings']);

        return array_values(array_filter([
            ['label' => __('Recently used'), 'recipes' => $recent],
            ['label' => __('For: :meal', ['meal' => Meal::typeLabel($type)]), 'recipes' => $matching],
        ], fn (array $section) => $section['recipes']->isNotEmpty()));
    }

    #[Computed]
    public function chosenRecipe(): ?Recipe
    {
        return $this->chosenRecipeId ? Recipe::query()->find($this->chosenRecipeId) : null;
    }

    /** Days after this one that can eat the leftovers. */
    #[Computed]
    public function leftoverDayOptions(): Collection
    {
        return collect(range(1, self::LEFTOVER_DAY_CHOICES))->map(fn (int $offset) => $this->day->copy()->addDays($offset));
    }

    public function recipeIdsOf(string $type): array
    {
        return array_column($this->meals[$type]['entries'], 'recipeId');
    }

    public function showOptionalType(string $type): void
    {
        if (in_array($type, self::OPTIONAL_TYPES, true)) {
            $this->shownOptionalTypes[] = $type;
        }
    }

    public function openRecipePicker(string $type): void
    {
        if (! in_array($type, Meal::TYPES, true)) {
            return;
        }

        $this->pickingType = $type;
        $this->reset('recipeSearch', 'chosenRecipeId', 'leftoverDates');
        $this->chosenMode = RecipeMealAssignment::MODE_SINGLE;
    }

    public function closeRecipePicker(): void
    {
        $this->reset('pickingType', 'recipeSearch', 'chosenRecipeId', 'leftoverDates', 'previewRecipeId');
    }

    public function previewRecipe(int $recipeId): void
    {
        $this->previewRecipeId = Recipe::query()->whereKey($recipeId)->exists() ? $recipeId : null;
    }

    public function closePreview(): void
    {
        $this->previewRecipeId = null;
    }

    /**
     * Step 1 → 2. Recipes that are a single meal anyway (servings ≤ what one
     * meal uses) have nothing to choose and are added right away.
     */
    public function chooseRecipe(int $recipeId): void
    {
        $recipe = Recipe::query()->active()->find($recipeId);

        if (! $recipe || $this->pickingType === null || in_array($recipeId, $this->recipeIdsOf($this->pickingType), true)) {
            return;
        }

        if ($recipe->servings <= Recipe::servingsPerMeal()) {
            $this->chosenRecipeId = $recipe->id;
            $this->chosenMode = RecipeMealAssignment::MODE_SINGLE;
            $this->addChosenRecipe();

            return;
        }

        $this->chosenRecipeId = $recipe->id;
        $this->chosenMode = RecipeMealAssignment::MODE_SINGLE;
        $this->leftoverDates = [];
        $this->previewRecipeId = null;
        unset($this->chosenRecipe);
    }

    public function backToRecipeList(): void
    {
        $this->reset('chosenRecipeId', 'leftoverDates');
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, [RecipeMealAssignment::MODE_SINGLE, RecipeMealAssignment::MODE_FULL], true)) {
            $this->chosenMode = $mode;
            $this->leftoverDates = [];
        }
    }

    public function toggleLeftoverDate(string $date): void
    {
        if (in_array($date, $this->leftoverDates, true)) {
            $this->leftoverDates = array_values(array_diff($this->leftoverDates, [$date]));

            return;
        }

        $valid = $this->leftoverDayOptions->contains(fn (CarbonInterface $day) => $day->toDateString() === $date);

        if ($valid && count($this->leftoverDates) < ($this->chosenRecipe?->maxLeftoverDays() ?? 0)) {
            $this->leftoverDates[] = $date;
            sort($this->leftoverDates);
        }
    }

    public function addChosenRecipe(): void
    {
        $recipe = $this->chosenRecipe ?? Recipe::query()->find($this->chosenRecipeId);
        $type = $this->pickingType;

        if (! $recipe || $type === null) {
            return;
        }

        $mode = $this->chosenMode === RecipeMealAssignment::MODE_FULL ? RecipeMealAssignment::MODE_FULL : RecipeMealAssignment::MODE_SINGLE;
        $leftoverDates = $mode === RecipeMealAssignment::MODE_FULL
            ? array_slice($this->leftoverDates, 0, $recipe->maxLeftoverDays())
            : [];

        DB::transaction(function () use ($recipe, $type, $mode, $leftoverDates) {
            $cooking = RecipeMealAssignment::query()->firstOrCreate(
                ['meal_id' => $this->mealFor($this->date, $type)->id, 'recipe_id' => $recipe->id],
                ['mode' => $mode],
            );

            foreach ($leftoverDates as $date) {
                RecipeMealAssignment::query()->firstOrCreate(
                    ['meal_id' => $this->mealFor($date, $type)->id, 'recipe_id' => $recipe->id],
                    ['mode' => RecipeMealAssignment::MODE_LEFTOVER, 'cooked_from_id' => $cooking->id],
                );
            }
        });

        $this->closeRecipePicker();
        $this->changed();
    }

    /** Removing a recipe cooked whole also removes its leftover days. */
    public function removeEntry(int $assignmentId): void
    {
        $assignment = RecipeMealAssignment::query()->find($assignmentId);

        if (! $assignment) {
            return;
        }

        DB::transaction(function () use ($assignment) {
            $mealIds = $assignment->leftovers()->pluck('meal_id')->push($assignment->meal_id);
            $assignment->leftovers()->delete();
            $assignment->delete();
            $this->deleteEmptyMeals($mealIds->all());
        });

        $this->changed();
    }

    public function startEditingNote(string $type): void
    {
        $this->editingNoteType = $type;
        $this->noteDraft = $this->meals[$type]['note'] ?? '';
    }

    public function saveNote(): void
    {
        $type = $this->editingNoteType;

        if ($type === null || ! in_array($type, Meal::TYPES, true)) {
            return;
        }

        $this->validate(['noteDraft' => ['nullable', 'string', 'max:255']]);
        $this->writeNote($type, trim($this->noteDraft));
        $this->editingNoteType = null;
    }

    public function clearNote(string $type): void
    {
        $this->writeNote($type, '');
        $this->editingNoteType = null;
    }

    private function writeNote(string $type, string $note): void
    {
        if ($note === trim($this->meals[$type]['note'])) {
            return;
        }

        $meal = $this->mealFor($this->date, $type);
        $meal->update(['note' => $note !== '' ? $note : null]);
        $this->deleteEmptyMeals([$meal->id]);
        $this->changed();
    }

    /** The single Meal row of (date, type), created when missing. */
    private function mealFor(string $date, string $type): Meal
    {
        return Meal::query()->whereDate(Meal::DATE_COLUMN, $date)->where(Meal::TYPE_COLUMN, $type)->orderBy('id')->first()
            ?? Meal::query()->create(['date' => Carbon::parse($date)->setTime(12, 0), 'type' => $type]);
    }

    private function deleteEmptyMeals(array $mealIds): void
    {
        Meal::query()->whereKey($mealIds)->whereNull(Meal::NOTE_COLUMN)->whereDoesntHave('recipes')->delete();
    }

    private function changed(): void
    {
        $this->loadMeals();
        unset($this->recentRecipes);
        $this->dispatch('saved');
    }
};
?>

<div @class(['space-y-3', 'lg:max-w-[720px]' => ! $inPanel])>
    @if ($inPanel)
    <div class="flex items-start justify-between gap-3 border-b border-line pb-3">
        <div>
            <p class="text-[13px] text-muted">{{ __('Day editor') }}</p>
            <h2 class="font-fraunces text-[24px] font-semibold leading-tight text-ink">{{ $this->title() }}</h2>
        </div>
        <button type="button" x-on:click="$dispatch('close-day-panel')" class="-mr-2 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}">
            <x-icon.x class="size-5" />
        </button>
    </div>
    @else
    <div class="space-y-1">
        <div class="flex items-center justify-between">
            <x-ui.back-button href="{{ route('meals.index') }}" :label="__('Week')" />
            <span
                x-data="{ show: false, timer: null }"
                x-on:saved.window="show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 1600)"
                x-show="show"
                x-transition.opacity
                x-cloak
                role="status"
                class="inline-flex items-center gap-1 text-[13px] font-medium text-forest">
                <x-icon.check class="size-3.5" />
                {{ __('Saved') }}
            </span>
        </div>
        <h1 class="font-fraunces text-[26px] font-semibold leading-tight text-ink">{{ $this->title() }}</h1>
    </div>

    <x-meal.week-strip :day="$this->day" route="meals.day.edit" />
    @endif

    <x-ui.card @class(['border-0 rounded-none bg-transparent' => $inPanel])>
        @foreach ($this->visibleTypes as $type)
        @php
            $row = $meals[$type];
            $hasNote = trim($row['note']) !== '';
            $isEditingNote = $editingNoteType === $type;
            $typeLabel = Meal::typeLabel($type);
        @endphp
        <section wire:key="meal-type-{{ $type }}" @class(['px-3.5 py-3 space-y-2.5', 'border-t border-divider' => ! $loop->first])>
            <div class="flex items-center justify-between min-h-8">
                <h2 class="text-[11px] font-semibold uppercase tracking-[0.09em] text-muted">{{ $typeLabel }}</h2>
                @if (! $hasNote && ! $isEditingNote)
                <button type="button" wire:click="startEditingNote('{{ $type }}')" aria-label="{{ __('Add a note to: :meal', ['meal' => $typeLabel]) }}" class="-my-1.5 inline-flex h-11 items-center gap-1.5 text-[13px] text-muted">
                    <x-icon.pencil class="size-4" />
                    {{ __('Note') }}
                </button>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach ($row['entries'] as $entry)
                <div wire:key="entry-{{ $entry['assignmentId'] }}" class="inline-flex items-center rounded-full bg-sand max-w-full">
                    <button type="button" x-data x-on:click="window.openRecipe('{{ route('recipes.show', $entry['recipeId']) }}')" class="min-w-0 pl-3.5 py-1.5 min-h-10 text-left">
                        <span class="block text-[14px] font-medium leading-tight text-ink truncate">{{ $entry['name'] }}</span>
                        @if ($entry['caption'])
                        <span class="block text-[11.5px] leading-tight text-muted truncate">{{ $entry['caption'] }}</span>
                        @endif
                    </button>
                    <button type="button" wire:click="removeEntry({{ $entry['assignmentId'] }})" class="flex size-10 shrink-0 items-center justify-center text-muted" aria-label="{{ __('Remove :name', ['name' => $entry['name']]) }}">
                        <x-icon.x class="size-4" />
                    </button>
                </div>
                @endforeach

                <button type="button" wire:click="openRecipePicker('{{ $type }}')" class="inline-flex h-10 items-center gap-1.5 rounded-full border border-dashed border-faint px-3.5 text-[14px] font-semibold text-terracotta">
                    <x-icon.plus class="size-4" />
                    {{ empty($row['entries']) ? __('Add recipe') : __('Add') }}
                </button>
            </div>

            @if ($isEditingNote)
            <form wire:submit="saveNote" x-data x-on:focusout="if (! $el.contains($event.relatedTarget)) $wire.saveNote()">
                <label for="note-{{ $type }}" class="sr-only">{{ __('Note') }}: {{ $typeLabel }}</label>
                <input
                    id="note-{{ $type }}"
                    type="text"
                    wire:model="noteDraft"
                    x-init="$nextTick(() => $el.focus())"
                    placeholder="{{ __('e.g. Eating out') }}"
                    maxlength="255"
                    enterkeyhint="done"
                    class="w-full h-11 rounded-xl border border-field bg-field-bg px-3 text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                <x-ui.field-error name="noteDraft" />
            </form>
            @elseif ($hasNote)
            <div class="rounded-[14px] border border-line bg-field-bg">
                <div class="flex items-center gap-2 pl-3 pr-1 min-h-11">
                    <x-icon.note class="size-4 text-muted" />
                    <button type="button" wire:click="startEditingNote('{{ $type }}')" class="min-w-0 text-left text-[14.5px] text-ink truncate">{{ $row['note'] }}</button>
                    <span class="shrink-0 rounded-full bg-sand px-2 py-0.5 text-[11px] text-muted">{{ __('not shopped') }}</span>
                    <span class="flex-1"></span>
                    <button type="button" wire:click="clearNote('{{ $type }}')" class="flex size-10 shrink-0 items-center justify-center text-muted" aria-label="{{ __('Remove note') }}">
                        <x-icon.x class="size-4" />
                    </button>
                </div>
                <a href="{{ route('recipes.create', ['note_date' => $date, 'note_type' => $type]) }}" wire:navigate class="flex items-center gap-2 border-t border-line px-3 min-h-11 text-[14px] font-semibold text-terracotta">
                    <x-icon.sparkle class="size-4" />
                    {{ __('Create a recipe from this note') }}
                </a>
            </div>
            @endif
        </section>
        @endforeach
    </x-ui.card>

    @if ($this->hiddenOptionalTypes)
    <div @class(['flex flex-wrap gap-2', 'px-3.5' => $inPanel])>
        @foreach ($this->hiddenOptionalTypes as $optionalType)
        <button type="button" wire:click="showOptionalType('{{ $optionalType }}')" class="inline-flex h-10 items-center gap-1.5 rounded-full border border-dashed border-faint px-3.5 text-[13.5px] font-semibold text-muted">
            <x-icon.plus class="size-4" />
            {{ Meal::typeLabel($optionalType) }}
        </button>
        @endforeach
    </div>
    @endif

    @if ($pickingType !== null)
    @php $sheetTitle = __('Add to: :meal', ['meal' => Meal::typeLabel($pickingType)]); @endphp
    <x-ui.sheet :title="$sheetTitle" close="closeRecipePicker" :wide="$previewRecipeId && ! $this->chosenRecipe" fullscreen>
        @if ($previewRecipeId && ! $this->chosenRecipe)
        <div class="space-y-3">
            <button type="button" wire:click="closePreview" class="-ml-2 inline-flex h-10 items-center gap-1 px-2 text-[15px] font-medium text-ink">
                <x-icon.chevron-left class="size-5" />
                {{ __('Back to the list') }}
            </button>
            <livewire:pages::recipe.show :recipe="Recipe::find($previewRecipeId)" :show-back-button="false" :key="'picker-preview-'.$previewRecipeId" />
            @php $previewAdded = in_array($previewRecipeId, $this->recipeIdsOf($pickingType), true); @endphp
            <div class="sticky bottom-0 -mx-4 border-t border-divider bg-white px-4 pt-3 pb-1">
                <x-ui.button wire:click="chooseRecipe({{ $previewRecipeId }})" :disabled="$previewAdded">
                    {{ $previewAdded ? __('Already added') : __('Choose this recipe') }}
                </x-ui.button>
            </div>
        </div>
        @elseif (! $this->chosenRecipe)
        <div class="space-y-3">
            <x-ui.search-input id="recipe-search" wire:model.live.debounce.200ms="recipeSearch" :placeholder="__('Search recipe')" bg="field" />

            @forelse ($this->pickerSections as $section)
            <div wire:key="picker-section-{{ $loop->index }}">
                <x-ui.section-label>{{ $section['label'] }}</x-ui.section-label>
                <div class="flex flex-col">
                    @foreach ($section['recipes'] as $pickerRecipe)
                    @php $already = in_array($pickerRecipe->id, $this->recipeIdsOf($pickingType), true); @endphp
                    <div wire:key="picker-recipe-{{ $section['label'] }}-{{ $pickerRecipe->id }}" class="flex items-center gap-1 min-h-[52px] border-b border-divider">
                        <button
                            type="button"
                            wire:click="chooseRecipe({{ $pickerRecipe->id }})"
                            @disabled($already)
                            class="flex flex-1 min-w-0 items-center gap-2 self-stretch px-1 text-left text-[15px] {{ $already ? 'text-muted' : 'text-ink' }}">
                            <span class="flex-1 min-w-0">{{ $pickerRecipe->name }}</span>
                            @if ($already)
                            <span class="text-[12px] text-muted">{{ __('added') }}</span>
                            @else
                            <span class="shrink-0 text-[12px] text-muted">{{ Recipe::servingsLabel((int) $pickerRecipe->servings) }}</span>
                            @endif
                        </button>
                        <button type="button" wire:click="previewRecipe({{ $pickerRecipe->id }})" class="flex size-11 shrink-0 items-center justify-center rounded-full text-ink-soft hover:bg-sand" aria-label="{{ __('Preview: :name', ['name' => $pickerRecipe->name]) }}" title="{{ __('Preview') }}">
                            <x-icon.eye class="size-[18px]" />
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <p class="px-1 text-[14px] text-muted">{{ __('No recipe with that name. You can add a note instead.') }}</p>
            @endforelse
        </div>
        @else
        @php
            $recipe = $this->chosenRecipe;
            $perMeal = Recipe::servingsPerMeal();
            $maxLeftovers = $recipe->maxLeftoverDays();
            $isFull = $chosenMode === RecipeMealAssignment::MODE_FULL;
            $modes = [
                RecipeMealAssignment::MODE_SINGLE => [__('One meal'), __(':portion of :servings go on the shopping list.', ['portion' => $perMeal, 'servings' => Recipe::servingsLabel($recipe->servings)])],
                RecipeMealAssignment::MODE_FULL => [__('Cooking the whole recipe'), __('Shopping for :servings goes on this day.', ['servings' => Recipe::servingsLabel($recipe->servings)])],
            ];
        @endphp
        <div class="space-y-3.5">
            <div class="flex items-center gap-2.5 rounded-[14px] bg-cream px-3 py-2.5">
                <span class="flex-1 min-w-0">
                    <span class="block text-[16px] font-semibold text-ink">{{ $recipe->name }}</span>
                    <span class="block text-[13px] text-muted">{{ __('Recipe for :servings', ['servings' => Recipe::servingsLabel($recipe->servings)]) }}</span>
                </span>
                <button type="button" wire:click="backToRecipeList" class="h-10 px-2 text-[13px] font-semibold text-terracotta">{{ __('Change') }}</button>
            </div>

            <div role="radiogroup" aria-label="{{ __('How much are you cooking') }}" class="flex flex-col gap-2">
                @foreach ($modes as $mode => [$label, $hint])
                @php $on = $chosenMode === $mode; @endphp
                <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="setMode('{{ $mode }}')"
                    @class(['flex items-center gap-3 min-h-[60px] px-3 py-2 rounded-[14px] border-[1.5px] text-left', 'border-ink bg-field-bg' => $on, 'border-line bg-white' => ! $on])>
                    <span @class(['flex size-5 shrink-0 items-center justify-center rounded-full border-2', 'border-ink' => $on, 'border-faint' => ! $on])>
                        @if ($on)<span class="size-2.5 rounded-full bg-ink"></span>@endif
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span class="text-[15px] font-semibold text-ink">{{ $label }}</span>
                        <span class="text-[13px] text-muted">{{ $hint }}</span>
                    </span>
                </button>
                @endforeach
            </div>

            @if ($isFull && $maxLeftovers > 0)
            <div class="space-y-2">
                <div class="flex items-baseline justify-between">
                    <x-ui.section-label>{{ __('We will eat the rest on') }}</x-ui.section-label>
                    <span class="text-[12px] text-muted">{{ __('planned :count of :total', ['count' => 1 + count($leftoverDates), 'total' => $maxLeftovers + 1]) }}</span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($this->leftoverDayOptions as $option)
                    @php
                        $value = $option->toDateString();
                        $on = in_array($value, $leftoverDates, true);
                        $can = $on || count($leftoverDates) < $maxLeftovers;
                    @endphp
                    <button type="button" wire:key="leftover-{{ $value }}" wire:click="toggleLeftoverDate('{{ $value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @disabled(! $can)
                        aria-label="{{ \Illuminate\Support\Str::ucfirst($option->copy()->locale(app()->getLocale())->isoFormat('dddd, D MMMM')) }}"
                        @class(['flex size-[52px] flex-col items-center justify-center gap-px rounded-[14px] border', 'bg-ink border-ink text-white' => $on, 'bg-white border-field text-ink' => ! $on && $can, 'bg-white border-field text-faint' => ! $can])>
                        <span class="text-[11px] font-medium">{{ \Illuminate\Support\Str::ucfirst($option->copy()->locale(app()->getLocale())->isoFormat('dd')) }}</span>
                        <span class="text-[15px] font-semibold">{{ $option->format('j') }}</span>
                    </button>
                    @endforeach
                </div>
                <p class="text-[12px] leading-normal text-muted">{{ __('On these days the recipe appears in the plan without extra shopping.') }}</p>
            </div>
            @endif

            <x-ui.button wire:click="addChosenRecipe">{{ $sheetTitle }}</x-ui.button>
        </div>
        @endif
    </x-ui.sheet>
    @endif
</div>
