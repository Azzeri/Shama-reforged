<?php

use App\Models\Recipe;
use App\Support\ShoppingList\Quantity;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public bool $showBackButton = true;

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

    /** Profiles (me / wife) that have at least one ingredient amount. */
    #[Computed]
    public function amountProfiles(): array
    {
        return array_values(array_filter(
            Recipe::NUTRITION_PROFILES,
            fn (string $profile) => $this->recipe->ingredients->contains(fn ($i) => $i->pivot->{"amount_{$profile}"} !== null),
        ));
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
};
?>

@php
    $profiles = $this->amountProfiles;
    $nutritionProfiles = collect(Recipe::NUTRITION_PROFILES)->filter(fn ($p) => $recipe->hasNutritionFor($p));
@endphp

<div>
    <div class="space-y-4">
        @if ($showBackButton)
        <div class="flex items-center justify-between gap-3">
            <x-ui.back-button href="{{ $this->backUrl }}" :label="__('Recipes')" />

            <a href="{{ route('recipes.edit', $recipe) }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2.5 font-inter text-[15px] font-semibold text-ink">
                <flux:icon.pencil class="size-4" />
                {{ __('Edit') }}
            </a>
        </div>
        @endif

        <div>
            <h1 class="font-fraunces text-[28px] font-semibold leading-tight text-ink">{{ $recipe->name }}</h1>
            @if ($recipe->tags->isNotEmpty())
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($recipe->tags->sortBy(fn ($tag) => $tag->isMealType() ? 0 : 1) as $tag)
                <x-recipe.tag-badge :tag="$tag" />
                @endforeach
            </div>
            @endif
        </div>

        @if ($recipe->ingredients->isNotEmpty())
        <section
            x-data="{
                portions: { me: 1, wife: 1 },
                units: @js($this->unitLabels),
                amount(value, unit, profile) {
                    const total = value * this.portions[profile];
                    const number = parseFloat(total.toFixed(total < 10 ? 1 : 0)).toString().replace('.', ',');
                    return `${number} ${this.units[unit] ?? unit ?? ''}`.trim();
                },
                grams(perPortion, profile) {
                    if (perPortion === null) return '';
                    const grams = perPortion * this.portions[profile];
                    return grams >= 1000
                        ? `≈ ${parseFloat((grams / 1000).toFixed(2)).toString().replace('.', ',')} kg`
                        : `≈ ${Math.round(grams)} g`;
                },
            }"
            class="rounded-[22px] border border-line bg-white overflow-hidden">
            <div class="flex items-end gap-3 px-4 pt-4 pb-2">
                <span class="flex-1 font-inter text-[12px] font-semibold uppercase tracking-[0.1em] text-muted">{{ __('Ingredients') }}</span>
                @foreach ($profiles as $profile)
                <span class="w-[76px] text-right font-inter text-[13px] font-semibold text-muted">{{ Recipe::nutritionProfileLabel($profile) }}</span>
                @endforeach
            </div>

            @if ($profiles)
            <div class="flex items-center gap-3 px-4 pb-3">
                <span class="flex-1 font-inter text-[14px] text-muted">{{ __('Portions') }}</span>
                @foreach ($profiles as $profile)
                <div class="w-[76px] flex items-center justify-end gap-2">
                    <button type="button" x-on:click="portions.{{ $profile }} = Math.max(1, portions.{{ $profile }} - 1)" class="px-1 font-inter text-lg leading-none text-muted" aria-label="{{ __('Fewer portions') }}">−</button>
                    <span class="font-inter text-[16px] font-semibold text-ink" x-text="portions.{{ $profile }}">1</span>
                    <button type="button" x-on:click="portions.{{ $profile }}++" class="px-1 font-inter text-lg leading-none text-terracotta-dark" aria-label="{{ __('More portions') }}">+</button>
                </div>
                @endforeach
            </div>
            @endif

            <div class="divide-y divide-divider border-t border-line">
                @foreach ($recipe->ingredients as $ingredient)
                @php $pivot = $ingredient->pivot; @endphp
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="flex-1 min-w-0 font-inter text-[15.5px] text-ink">{{ $ingredient->name }}</span>

                    @if ($profiles)
                    @foreach ($profiles as $profile)
                    @php $value = $pivot->{"amount_{$profile}"}; @endphp
                    <div class="w-[76px] text-right">
                        @if ($value !== null)
                        <div class="font-inter text-[15px] text-ink" x-text="amount({{ $value }}, @js($pivot->unit), '{{ $profile }}')">{{ Quantity::format($value, $pivot->unit) }}</div>
                        @if (($grams = $ingredient->gramsFor($pivot->unit, $value)) !== null)
                        <div class="font-inter text-[11.5px] text-muted" x-text="grams({{ $grams }}, '{{ $profile }}')"></div>
                        @endif
                        @else
                        <span class="text-ink/30">–</span>
                        @endif
                    </div>
                    @endforeach
                    @elseif ($pivot->quantity)
                    <span class="font-inter text-[15px] text-ink">{{ $pivot->quantity }}</span>
                    @endif
                </div>
                @endforeach
            </div>
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
                        this.lock.addEventListener('release', () => { this.awake = false; });
                    } catch (e) {
                        this.awake = false;
                    }
                },
                async release() {
                    if (this.lock) {
                        await this.lock.release();
                        this.lock = null;
                    }
                    this.awake = false;
                },
                toggle() {
                    this.awake ? this.release() : this.acquire();
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
            class="rounded-[22px] border border-line bg-white px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <span class="font-inter text-[12px] font-semibold uppercase tracking-[0.1em] text-muted">{{ __('Preparation') }}</span>

                <label x-show="supported" class="inline-flex items-center gap-2.5 cursor-pointer">
                    <span class="font-inter text-[13.5px] text-muted">{{ __('Keep screen on') }}</span>
                    <button
                        type="button"
                        role="switch"
                        x-on:click="toggle()"
                        x-bind:aria-checked="awake"
                        class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                        x-bind:class="awake ? 'bg-forest' : 'bg-ink/20'">
                        <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform" x-bind:class="awake && 'translate-x-5'"></span>
                    </button>
                </label>
            </div>

            @if ($this->steps)
            <ol class="mt-3 list-decimal pl-5 space-y-3 font-fraunces italic text-[16.5px] leading-relaxed text-ink marker:font-semibold">
                @foreach ($this->steps as $step)
                <li class="pl-1">{{ $step }}</li>
                @endforeach
            </ol>
            @endif

            @if (filled($recipe->link))
            <a href="{{ $recipe->link }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-1 font-inter text-[14.5px] font-semibold text-terracotta-dark">
                {{ __('Recipe source') }}
                <flux:icon.arrow-up-right class="size-3.5" />
            </a>
            @endif
        </section>
        @endif

        @if ($nutritionProfiles->isNotEmpty())
        <section class="rounded-[22px] border border-line bg-white px-4 py-4">
            <div class="font-inter text-[12px] font-semibold uppercase tracking-[0.1em] text-muted">{{ __('Nutrition per serving') }}</div>

            <table class="mt-2 w-full font-inter">
                <thead>
                    <tr class="text-[12.5px] text-muted">
                        <th></th>
                        <th class="py-1 text-right font-normal">kcal</th>
                        <th class="py-1 text-right font-normal">{{ __('protein') }}</th>
                        <th class="py-1 text-right font-normal">{{ __('carbs') }}</th>
                        <th class="py-1 text-right font-normal">{{ __('fat') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-divider">
                    @foreach ($nutritionProfiles as $profile)
                    @php $n = $recipe->nutritionFor($profile); @endphp
                    <tr class="text-[15px] text-ink">
                        <td class="py-2.5 font-semibold">{{ Recipe::nutritionProfileLabel($profile) }}</td>
                        <td class="py-2.5 text-right font-fraunces text-[19px] font-semibold">{{ $n['calories'] ?? '–' }}</td>
                        <td class="py-2.5 text-right">{{ $n['protein'] !== null ? round($n['protein']) . ' g' : '–' }}</td>
                        <td class="py-2.5 text-right">{{ $n['carbs'] !== null ? round($n['carbs']) . ' g' : '–' }}</td>
                        <td class="py-2.5 text-right">{{ $n['fat'] !== null ? round($n['fat']) . ' g' : '–' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        @endif
    </div>
</div>
