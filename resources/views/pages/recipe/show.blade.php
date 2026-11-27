<?php

use App\Models\Recipe;
use App\Support\ShoppingList\Quantity;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public bool $showBackButton = true;

    /** Desktop recipe list: shown in the right-hand pane (no back link, actions beside the title). */
    public bool $embedded = false;

    public function mount(): void
    {
        $this->recipe->loadMissing(['tags', 'ingredients']);
    }

    public function render()
    {
        return $this->view()->title($this->recipe->name);
    }

    #[Computed]
    public function backUrl(): string
    {
        $back = request()->query('back');

        if (is_string($back) && str_starts_with($back, '/') && ! str_starts_with($back, '//')) {
            return $back;
        }

        return route('recipes.index');
    }

    /** Preparation text split into steps (one per non-empty line, numbering stripped). */
    #[Computed]
    public function steps(): array
    {
        return collect(preg_split('/\R/u', (string) $this->recipe->content))
            ->map(fn (string $line) => trim(preg_replace('/^\s*\d+[.)]\s*/u', '', $line)))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    /** Unit code => display label, for the JS that rescales amounts. */
    #[Computed]
    public function unitLabels(): array
    {
        return collect(\App\Models\RecipeIngredientAssignment::UNITS)
            ->mapWithKeys(fn (string $unit) => [$unit => Quantity::unitLabel($unit)])
            ->all();
    }

    /** Servings label forms for the stepper ("1 porcja" / "2 porcje" / "5 porcji"). */
    #[Computed]
    public function servingForms(): array
    {
        return collect([1, 2, 5, 22])->mapWithKeys(fn (int $n) => [$n => trans_choice(':count serving|:count servings', $n, ['count' => ''])])->all();
    }

    public function archive(): void
    {
        $this->recipe->update(['archived_at' => now()]);

        if ($this->embedded) {
            $this->dispatch('recipe-archived');
        }
    }

    public function unarchive(): void
    {
        $this->recipe->update(['archived_at' => null]);
    }
};
?>

@php
    $base = max(1, (int) $recipe->servings);
@endphp

<div @class(['space-y-4', 'lg:max-w-[1040px]' => ! $embedded])>
    @if ($showBackButton && ! $embedded)
    <div class="flex items-center justify-between gap-3">
        <x-ui.back-button href="{{ $this->backUrl }}" :label="__('Recipes')" />
        <div class="flex items-center gap-2">
            <button type="button" x-data x-on:click="window.shareOrCopy({ title: @js($recipe->name), url: @js(route('recipes.show', $recipe)) })" class="flex size-10 items-center justify-center rounded-full border border-field bg-surface text-ink" aria-label="{{ __('Share') }}">
                <x-icon.share class="size-[18px]" />
            </button>
            <x-ui.pill variant="outline" icon="pencil" :href="route('recipes.edit', $recipe)">{{ __('Edit') }}</x-ui.pill>
        </div>
    </div>
    @endif

    <div class="space-y-2">
        @if ($embedded)
        <div class="flex items-start gap-3">
            <h2 class="flex-1 font-fraunces text-[30px] font-semibold leading-tight text-ink">{{ $recipe->name }}</h2>
            <x-ui.pill variant="outline" icon="pencil" :href="route('recipes.edit', $recipe)" class="border-field! text-ink! border!">{{ __('Edit') }}</x-ui.pill>
            <button type="button" wire:click="archive" class="h-10 rounded-full border border-field bg-surface px-4 text-[14px] font-semibold text-ink">{{ __('To the archive') }}</button>
        </div>
        @else
        <h1 data-large-title class="font-fraunces text-[26px] lg:text-[32px] font-semibold leading-tight text-ink">{{ $recipe->name }}</h1>
        @endif
        <x-recipe.labels :recipe="$recipe" />
        @if ($recipe->isArchived())
        <p class="inline-flex items-center gap-1.5 rounded-full bg-sand px-2.5 py-1 text-[12.5px] text-ink-soft"><x-icon.archive class="size-3.5" /> {{ __('In the archive') }}</p>
        @endif
    </div>

    <div @class(['space-y-4', 'lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] lg:items-start lg:gap-5 lg:space-y-0' => ! $embedded])>
    @if ($recipe->ingredients->isNotEmpty())
    <section
        x-data="{
            base: {{ $base }},
            servings: {{ $base }},
            units: @js($this->unitLabels),
            format(value) {
                return parseFloat(value.toFixed(value < 10 ? 1 : 0)).toString().replace('.', ',');
            },
            amount(value, unit) {
                const total = value * this.servings / this.base;
                return `${this.format(total)} ${this.units[unit] ?? unit ?? ''}`.trim();
            },
            grams(perRecipe) {
                const grams = perRecipe * this.servings / this.base;
                return grams >= 1000 ? `≈ ${parseFloat((grams / 1000).toFixed(2)).toString().replace('.', ',')} kg` : `≈ ${Math.round(grams)} g`;
            },
            label(n) {
                const forms = @js(array_values($this->servingForms));
                const form = n === 1 ? forms[0] : ([2, 3, 4].includes(n % 10) && ! [12, 13, 14].includes(n % 100) ? forms[1] : forms[2]);
                return `${n} ${form.trim()}`;
            },
        }"
        class="rounded-[20px] border border-line bg-surface overflow-hidden">
        <div class="flex items-center gap-3 px-4 pt-3.5 pb-3">
            <div class="flex-1 min-w-0">
                <h2 class="text-[11px] font-semibold uppercase tracking-[0.09em] text-muted">{{ __('Ingredients') }}</h2>
                <p class="text-[13px] text-muted">{{ __('Recipe for :servings', ['servings' => Recipe::servingsLabel($base)]) }}</p>
            </div>
            <div class="inline-flex h-10 items-center rounded-full border border-field bg-surface">
                <button type="button" x-on:click="servings = Math.max(1, servings - 1)" x-bind:disabled="servings <= 1" aria-label="{{ __('Fewer servings') }}" class="flex size-10 items-center justify-center text-ink disabled:text-faint">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>
                </button>
                <span class="min-w-[4.5rem] text-center text-[14px] font-semibold text-ink tabular-nums" x-text="label(servings)" aria-live="polite">{{ Recipe::servingsLabel($base) }}</span>
                <button type="button" x-on:click="servings++" aria-label="{{ __('More servings') }}" class="flex size-10 items-center justify-center text-ink">
                    <x-icon.plus class="size-4" />
                </button>
            </div>
        </div>

        <ul class="divide-y divide-divider border-t border-divider">
            @foreach ($recipe->ingredients as $ingredient)
            @php
                $pivot = $ingredient->pivot;
                $total = $pivot->totalAmount();
            @endphp
            <li class="flex items-start gap-3 px-4 py-2.5 min-h-12">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <span class="text-[15px] text-ink">{{ $ingredient->name }}</span>
                        @if ($pivot->is_optional)
                        <span class="rounded-full border border-dashed border-faint px-1.5 text-[11px] text-muted">{{ __('optionally') }}</span>
                        @endif
                    </div>
                    @if (filled($pivot->substitute))
                    <div class="text-[13px] text-muted">{{ __('or') }}: {{ $pivot->substitute }}</div>
                    @endif
                </div>
                <div class="shrink-0 text-right">
                    @if ($total !== null && $pivot->unit)
                    <div class="text-[15px] text-ink tabular-nums" x-text="amount({{ $total }}, @js($pivot->unit))">{{ Quantity::format($total, $pivot->unit) }}</div>
                    @if (($grams = $ingredient->gramsFor($pivot->unit, $total)) !== null && $pivot->unit !== 'g')
                    <div class="text-[11.5px] text-muted" x-text="grams({{ $grams }})"></div>
                    @endif
                    @elseif (filled($pivot->quantity))
                    <span class="text-[15px] text-ink">{{ $pivot->quantity }}</span>
                    @endif
                </div>
            </li>
            @endforeach
        </ul>
    </section>
    @endif

    @if ($this->steps || filled($recipe->link))
    <section
        x-data="{
            awake: false,
            supported: typeof navigator !== 'undefined' && 'wakeLock' in navigator,
            lock: null,
            async acquire() {
                try {
                    this.lock = await navigator.wakeLock.request('screen');
                    this.awake = true;
                    this.lock.addEventListener('release', () => { this.lock = null; });
                } catch (e) {
                    this.awake = false;
                }
            },
            async release() {
                this.awake = false;
                if (this.lock) {
                    const lock = this.lock;
                    this.lock = null;
                    await lock.release();
                }
            },
            init() {
                this.visibilityHandler = () => {
                    if (this.awake && ! this.lock && document.visibilityState === 'visible') this.acquire();
                };
                document.addEventListener('visibilitychange', this.visibilityHandler);
            },
            // Leaving the recipe (wire:navigate keeps the same document)
            // must not keep the screen awake on the next page.
            destroy() {
                document.removeEventListener('visibilitychange', this.visibilityHandler);
                this.release();
            },
        }"
        class="rounded-[20px] border border-line bg-surface px-4 py-3.5 space-y-3">
        <h2 class="text-[11px] font-semibold uppercase tracking-[0.09em] text-muted">{{ __('Preparation') }}</h2>

        @if ($this->steps)
        <ol class="space-y-2.5">
            @foreach ($this->steps as $step)
            <li class="flex gap-2.5">
                <span class="w-5 shrink-0 font-fraunces text-[16px] font-semibold text-muted tabular-nums">{{ $loop->iteration }}.</span>
                <span class="text-[15px] leading-relaxed text-prose">{{ $step }}</span>
            </li>
            @endforeach
        </ol>
        @endif

        <template x-if="supported">
            <label class="flex items-center gap-3 min-h-11 border-t border-divider pt-2 cursor-pointer">
                <span class="flex-1 text-[15px] text-ink">{{ __('Keep the screen on') }}</span>
                <input type="checkbox" role="switch" class="peer sr-only" x-bind:checked="awake" x-on:change="$event.target.checked ? acquire() : release()" />
                <span class="relative h-7 w-12 shrink-0 rounded-full bg-field transition-colors peer-checked:bg-sage peer-focus-visible:ring-2 peer-focus-visible:ring-ink after:absolute after:left-0.5 after:top-0.5 after:size-6 after:rounded-full after:bg-surface after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
            </label>
        </template>

        @if (filled($recipe->link))
        <a href="{{ $recipe->link }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center gap-1.5 text-[15px] font-semibold text-terracotta">
            {{ __('Recipe source') }}
            <x-icon.arrow-up-right class="size-4" />
        </a>
        @endif
    </section>
    @endif

    </div>

    @if ($showBackButton && ! $embedded)
    <div class="pt-2 space-y-2 lg:max-w-[420px]">
        @if ($recipe->isArchived())
        <x-ui.button variant="outline" wire:click="unarchive">
            <x-icon.undo class="size-4" />
            {{ __('Restore from the archive') }}
        </x-ui.button>
        <p class="text-[13px] text-muted text-center">{{ __('The recipe will be back on the list and in planning.') }}</p>
        @else
        <x-ui.button variant="outline" wire:click="archive">
            <x-icon.archive class="size-4" />
            {{ __('Move to the archive') }}
        </x-ui.button>
        <p class="text-[13px] text-muted text-center">{{ __('It disappears from the list and from planning; nothing is deleted.') }}</p>
        @endif
    </div>
    @endif
</div>
