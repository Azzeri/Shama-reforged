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

/*
 * Day editor. Every change (adding/removing a recipe, editing a note) is
 * persisted immediately — there is no Save button.
 */
new #[Layout('layouts::app')] class extends Component {
    /**
     * Meal types shown only when they already have content or were added
     * by hand — not part of the default list.
     */
    public const OPTIONAL_TYPES = ['dessert'];

    public string $date;

    /** @var array<string, array{recipeIds: list<int>, note: string}> */
    public array $meals = [];

    /** Optional meal types the user has opened for this day. */
    public array $shownOptionalTypes = [];

    public ?string $editingNoteType = null;

    public ?string $pickingType = null;

    public string $recipeSearch = '';

    public ?int $previewingRecipeId = null;

    /** "type:recipeId" of the recipe whose action sheet is open. */
    public ?string $actionKey = null;

    public bool $showRepeatForm = false;

    public string $repeatDayOffset = '';

    public string $repeatMealType = '';

    public function mount(string $date): void
    {
        $this->date = Carbon::parse($date)->toDateString();

        $existingByType = Meal::query()
            ->with('recipes:id')
            ->whereDate(Meal::DATE_COLUMN, $this->date)
            ->orderBy('id')
            ->get()
            ->groupBy(Meal::TYPE_COLUMN);

        $this->meals = collect(Meal::TYPES)
            ->mapWithKeys(function (string $type) use ($existingByType) {
                $mealsOfType = $existingByType->get($type, collect());

                return [$type => [
                    'recipeIds' => $mealsOfType
                        ->flatMap(fn (Meal $meal) => $meal->recipes->pluck('id'))
                        ->unique()
                        ->values()
                        ->all(),
                    'note' => $mealsOfType->first()?->note ?? '',
                ]];
            })
            ->all();
    }

    public function render()
    {
        return $this->view()
            ->title(\Illuminate\Support\Str::ucfirst($this->day->locale(app()->getLocale())->isoFormat('dddd, D MMMM')));
    }

    #[Computed]
    public function day(): CarbonInterface
    {
        return Carbon::parse($this->date)->startOfDay();
    }

    #[Computed]
    public function weekDays(): Collection
    {
        $weekStart = $this->day->copy()->startOfWeek();

        return collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));
    }

    #[Computed]
    public function visibleTypes(): array
    {
        return array_values(array_filter(Meal::TYPES, fn (string $type) => ! in_array($type, self::OPTIONAL_TYPES, true)
            || in_array($type, $this->shownOptionalTypes, true)
            || ! empty($this->meals[$type]['recipeIds'])
            || trim($this->meals[$type]['note']) !== ''));
    }

    #[Computed]
    public function hiddenOptionalTypes(): array
    {
        return array_values(array_diff(self::OPTIONAL_TYPES, $this->visibleTypes));
    }

    /**
     * Day options for the "repeat this recipe" form, offset (0-6) from the
     * start of the week the edited day falls in.
     */
    #[Computed]
    public function weekDayOptions(): Collection
    {
        return $this->weekDays->map(fn (CarbonInterface $day, int $offset) => [
            'offset' => $offset,
            'label' => \Illuminate\Support\Str::ucfirst($day->copy()->locale(app()->getLocale())->isoFormat('dddd, D MMM')),
        ]);
    }

    #[Computed]
    public function recipesById(): Collection
    {
        $ids = collect($this->meals)->flatMap(fn (array $row) => $row['recipeIds'])->unique()->values();

        return Recipe::query()->whereKey($ids)->get()->keyBy('id');
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
            ->whereHas('tags', function ($tagQuery) use ($type) {
                $tagQuery->where('category', Tag::MEAL_TYPE)
                    ->where('name', Meal::tagName($type));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function actionRecipe(): ?Recipe
    {
        if ($this->actionKey === null) {
            return null;
        }

        [, $recipeId] = explode(':', $this->actionKey);

        return $this->recipesById->get((int) $recipeId);
    }

    public function showOptionalType(string $type): void
    {
        if (in_array($type, self::OPTIONAL_TYPES, true)) {
            $this->shownOptionalTypes[] = $type;
        }
    }

    public function openRecipePicker(string $type): void
    {
        $this->pickingType = $type;
        $this->recipeSearch = '';
    }

    public function closeRecipePicker(): void
    {
        $this->pickingType = null;
        $this->recipeSearch = '';
    }

    public function selectRecipeForPicker(int $recipeId): void
    {
        $type = $this->pickingType;

        if ($type !== null && Recipe::query()->whereKey($recipeId)->exists()
            && ! in_array($recipeId, $this->meals[$type]['recipeIds'], true)) {
            $this->meals[$type]['recipeIds'][] = $recipeId;
            $this->persist($type);
            unset($this->recipesById);
        }

        $this->closeRecipePicker();
    }

    public function removeRecipe(string $type, int $recipeId): void
    {
        $this->meals[$type]['recipeIds'] = array_values(array_filter(
            $this->meals[$type]['recipeIds'],
            fn (int $id) => $id !== $recipeId,
        ));

        $this->persist($type);
        $this->closeActionSheet();
    }

    public function startEditingNote(string $type): void
    {
        $this->editingNoteType = $type;
    }

    public function saveNote(string $type): void
    {
        $this->validate(["meals.$type.note" => ['nullable', 'string', 'max:255']]);

        $this->meals[$type]['note'] = trim($this->meals[$type]['note']);
        $this->editingNoteType = null;
        $this->persist($type);
    }

    public function clearNote(string $type): void
    {
        $this->meals[$type]['note'] = '';
        $this->editingNoteType = null;
        $this->persist($type);
    }

    public function showRecipePreview(int $recipeId): void
    {
        $this->previewingRecipeId = $recipeId;
    }

    public function closeRecipePreview(): void
    {
        $this->previewingRecipeId = null;
    }

    public function openActionSheet(string $type, int $recipeId): void
    {
        $this->actionKey = "{$type}:{$recipeId}";
        $this->showRepeatForm = false;
        $this->repeatDayOffset = '';
        $this->repeatMealType = '';
        $this->resetErrorBag();
    }

    public function closeActionSheet(): void
    {
        $this->actionKey = null;
        $this->showRepeatForm = false;
        unset($this->actionRecipe);
    }

    /**
     * Attaches the recipe to another (day, meal type) slot, so shared
     * ingredients merge on the shopping list once both days are generated.
     */
    public function repeatRecipe(): void
    {
        $this->validate([
            'repeatDayOffset' => ['required', Rule::in(range(0, 6))],
            'repeatMealType' => ['required', Rule::in(Meal::TYPES)],
        ]);

        $recipe = $this->actionRecipe;

        if (! $recipe) {
            return;
        }

        $targetDate = $this->weekDays[(int) $this->repeatDayOffset];

        $targetMeal = Meal::query()
            ->whereDate(Meal::DATE_COLUMN, $targetDate->toDateString())
            ->where(Meal::TYPE_COLUMN, $this->repeatMealType)
            ->first()
            ?? Meal::create(['date' => $targetDate->copy()->setTime(12, 0), 'type' => $this->repeatMealType]);

        $targetMeal->recipes()->syncWithoutDetaching([$recipe->id]);

        if ($targetDate->isSameDay($this->day) && ! in_array($recipe->id, $this->meals[$this->repeatMealType]['recipeIds'], true)) {
            $this->meals[$this->repeatMealType]['recipeIds'][] = $recipe->id;
        }

        $this->closeActionSheet();
    }

    /**
     * Writes one meal type of this day to the database: a single Meal row
     * per (day, type), deleted when it has neither recipes nor a note.
     */
    private function persist(string $type): void
    {
        $row = $this->meals[$type];
        $note = trim($row['note']);

        $existing = Meal::query()
            ->whereDate(Meal::DATE_COLUMN, $this->date)
            ->where(Meal::TYPE_COLUMN, $type)
            ->orderBy('id')
            ->get();

        $meal = $existing->shift();

        if ($existing->isNotEmpty()) {
            Meal::query()->whereKey($existing->pluck('id'))->delete();
        }

        if (empty($row['recipeIds']) && $note === '') {
            $meal?->delete();

            return;
        }

        $meal ??= new Meal(['date' => $this->day->copy()->setTime(12, 0), 'type' => $type]);
        $meal->note = $note !== '' ? $note : null;
        $meal->save();
        $meal->recipes()->sync($row['recipeIds']);
    }
};
?>

<div>
    <div class="space-y-4">
        <x-ui.back-button href="{{ route('meals.index') }}" :label="__('Week')" />

        <h1 class="font-fraunces text-[28px] leading-tight font-semibold text-ink">{{ \Illuminate\Support\Str::ucfirst($this->day->locale(app()->getLocale())->isoFormat('dddd, D MMMM')) }}</h1>

        <x-meal.week-strip :day="$this->day" route="meals.day.edit" />

        <div class="bg-white rounded-[22px] border border-line divide-y divide-divider">
            @foreach ($this->visibleTypes as $type)
            @php
                $row = $meals[$type];
                $hasNote = trim($row['note']) !== '';
                $isEditingNote = $editingNoteType === $type;
            @endphp
            <div wire:key="meal-type-{{ $type }}" class="px-4 py-4 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-inter text-[12px] font-semibold uppercase tracking-[0.12em] text-muted">{{ Meal::typeLabel($type) }}</span>

                    @if (! $hasNote && ! $isEditingNote)
                    <button type="button" wire:click="startEditingNote('{{ $type }}')" class="inline-flex items-center gap-1.5 font-inter text-[14px] text-muted">
                        <flux:icon.pencil class="size-4" />
                        {{ __('Note') }}
                    </button>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($row['recipeIds'] as $recipeId)
                    @if ($recipe = $this->recipesById->get($recipeId))
                    <div wire:key="meal-{{ $type }}-recipe-{{ $recipeId }}" class="inline-flex items-center rounded-full bg-sand max-w-full">
                        <button
                            type="button"
                            x-data="{ timer: null }"
                            x-on:touchstart.passive="timer = setTimeout(() => { timer = null; $wire.openActionSheet('{{ $type }}', {{ $recipeId }}) }, 450)"
                            x-on:touchmove.passive="clearTimeout(timer)"
                            x-on:touchend="clearTimeout(timer)"
                            x-on:mousedown="timer = setTimeout(() => { timer = null; $wire.openActionSheet('{{ $type }}', {{ $recipeId }}) }, 450)"
                            x-on:mouseup="clearTimeout(timer)"
                            x-on:mouseleave="clearTimeout(timer)"
                            x-on:contextmenu.prevent
                            class="pl-3.5 py-2 font-inter text-[14.5px] font-semibold text-ink text-left truncate select-none [-webkit-touch-callout:none]">
                            {{ $recipe->name }}
                        </button>
                        <button type="button" wire:click="removeRecipe('{{ $type }}', {{ $recipeId }})" class="px-2.5 py-2 text-muted" aria-label="{{ __('Remove') }}">
                            <flux:icon.x-mark class="size-4" />
                        </button>
                    </div>
                    @endif
                    @endforeach

                    <button
                        type="button"
                        wire:click="openRecipePicker('{{ $type }}')"
                        class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-field px-3.5 py-2 font-inter text-[14.5px] font-semibold text-terracotta-dark">
                        <flux:icon.plus class="size-4" />
                        {{ empty($row['recipeIds']) ? __('Add recipe') : __('Add') }}
                    </button>
                </div>

                @if ($isEditingNote)
                <div>
                    <input
                        type="text"
                        wire:model="meals.{{ $type }}.note"
                        wire:keydown.enter="saveNote('{{ $type }}')"
                        wire:blur="saveNote('{{ $type }}')"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="{{ __('e.g. Eating out') }}"
                        maxlength="255"
                        class="w-full rounded-xl border border-field bg-cream px-3.5 py-2.5 font-inter text-base text-ink focus:outline-none focus:ring-2 focus:ring-terracotta/30" />
                    <x-ui.field-error name="meals.{{ $type }}.note" />
                </div>
                @elseif ($hasNote)
                <div class="flex items-center gap-2.5 rounded-xl border border-line bg-cream px-3.5 py-2.5">
                    <flux:icon.document class="size-4 text-muted shrink-0" />
                    <button type="button" wire:click="startEditingNote('{{ $type }}')" class="flex-1 text-left font-inter text-[14.5px] text-ink truncate">
                        {{ $row['note'] }}
                    </button>
                    <button type="button" wire:click="clearNote('{{ $type }}')" class="text-muted shrink-0" aria-label="{{ __('Remove') }}">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        @if ($this->hiddenOptionalTypes)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->hiddenOptionalTypes as $optionalType)
            <button
                type="button"
                wire:click="showOptionalType('{{ $optionalType }}')"
                class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-field px-3.5 py-2 font-inter text-[13.5px] font-semibold text-muted">
                <flux:icon.plus class="size-4" />
                {{ Meal::typeLabel($optionalType) }}
            </button>
            @endforeach
        </div>
        @endif
    </div>

    @if ($actionKey && $this->actionRecipe)
    @php [$actionType] = explode(':', $actionKey); @endphp
    <div
        class="fixed inset-0 z-[55] bg-[rgba(27,21,16,0.45)] flex items-end justify-center overscroll-contain"
        wire:click.self="closeActionSheet">
        <div class="w-full max-w-xl rounded-t-[24px] bg-cream max-h-[88dvh] overflow-y-auto overscroll-contain p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] space-y-4">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ $this->actionRecipe->name }}</h3>
                <button type="button" wire:click="closeActionSheet" class="text-ink/25 hover:text-muted p-1.5 text-lg leading-none">✕</button>
            </div>

            <x-recipe.nutrition :recipe="$this->actionRecipe" compact />

            @if ($showRepeatForm)
            <div class="rounded-xl border border-line bg-white p-3.5 space-y-3">
                <div class="grid grid-cols-2 gap-2.5">
                    <select wire:model="repeatDayOffset" class="w-full border-[1.5px] border-field bg-white rounded-2xl px-3.5 py-3 font-inter text-base sm:text-sm text-ink">
                        <option value="">{{ __('Day') }}</option>
                        @foreach ($this->weekDayOptions as $option)
                        <option value="{{ $option['offset'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    <select wire:model="repeatMealType" class="w-full border-[1.5px] border-field bg-white rounded-2xl px-3.5 py-3 font-inter text-base sm:text-sm text-ink">
                        <option value="">{{ __('Meal type') }}</option>
                        @foreach (Meal::TYPES as $optionType)
                        <option value="{{ $optionType }}">{{ Meal::typeLabel($optionType) }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.field-error name="repeatDayOffset" />
                <x-ui.field-error name="repeatMealType" />
                <button type="button" wire:click="repeatRecipe" class="w-full bg-forest text-white rounded-xl py-3 font-inter text-sm font-semibold">
                    {{ __('Add') }}
                </button>
            </div>
            @endif

            <div class="grid gap-2">
                <a
                    href="{{ route('recipes.show', $this->actionRecipe) }}?back={{ urlencode(route('meals.day.edit', $date, false)) }}"
                    wire:navigate
                    class="flex items-center justify-center gap-2 bg-white border border-line rounded-[14px] py-3 font-inter text-sm font-semibold text-ink">
                    <flux:icon.book-open class="size-4" />
                    {{ __('Open recipe') }}
                </a>
                @unless ($showRepeatForm)
                <button type="button" wire:click="$set('showRepeatForm', true)" class="flex items-center justify-center gap-2 bg-white border border-line rounded-[14px] py-3 font-inter text-sm font-semibold text-forest">
                    <flux:icon.arrow-path class="size-4" />
                    {{ __('Repeat on another day') }}
                </button>
                @endunless
                <button type="button" wire:click="removeRecipe('{{ $actionType }}', {{ $this->actionRecipe->id }})" class="flex items-center justify-center gap-2 py-3 font-inter text-sm font-semibold text-terracotta-dark">
                    <flux:icon.trash class="size-4" />
                    {{ __('Remove from meal') }}
                </button>
            </div>
        </div>
    </div>
    @endif

    @if ($pickingType !== null)
    <div
        x-data="{
            init() { document.documentElement.classList.add('overflow-hidden') },
            destroy() { document.documentElement.classList.remove('overflow-hidden') },
        }"
        class="fixed inset-0 z-50 bg-cream overflow-y-auto overscroll-contain">
        <div class="max-w-xl mx-auto px-4 pt-[calc(1.5rem+env(safe-area-inset-top))] pb-[calc(2rem+env(safe-area-inset-bottom))]">
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

            <div class="rounded-2xl border border-line overflow-hidden bg-white">
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
                    @touchstart.passive="startPress"
                    @touchmove.passive="endPress"
                    @touchend="endPress"
                    @click="handleClick"
                    @contextmenu.prevent
                    class="w-full text-left select-none px-4 py-3.5 font-inter text-[14.5px] font-semibold text-ink border-b border-line last:border-b-0 hover:bg-sand/40">
                    {{ $pickerRecipe->name }}
                </button>
                @empty
                <div class="px-4 py-8 text-center font-inter text-sm text-muted">
                    {{ __('No recipes found.') }}
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if ($previewingRecipeId !== null)
    <div
        class="fixed inset-0 z-[60] bg-[rgba(27,21,16,0.45)] flex items-end justify-center overscroll-contain"
        wire:click.self="closeRecipePreview">
        <div class="w-full max-w-xl rounded-t-[24px] bg-cream max-h-[88dvh] overflow-y-auto overscroll-contain shadow-[0_-10px_40px_rgba(0,0,0,0.3)] p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))]">
            <div class="flex justify-end -mt-1 -me-1 mb-1">
                <button
                    type="button"
                    wire:click="closeRecipePreview"
                    class="text-ink/25 hover:text-muted p-1.5 text-lg leading-none">✕</button>
            </div>

            <livewire:pages::recipe.show :recipe="Recipe::find($previewingRecipeId)" :show-back-button="false" :key="'recipe-preview-'.$previewingRecipeId" />
        </div>
    </div>
    @endif
</div>
