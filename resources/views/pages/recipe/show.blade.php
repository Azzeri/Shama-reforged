<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public bool $showBackButton = true;

    public function render()
    {
        return $this->view()
            ->title($this->recipe->name);
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

    public function delete(): void
    {
        $this->recipe->delete();

        Flux::toast(variant: 'success', text: __('Recipe deleted.'));

        $this->redirectRoute('recipes.index', navigate: true);
    }
};
?>

<div>
    <div class="space-y-5">
        @if ($showBackButton)
        <x-ui.back-button href="{{ $this->backUrl }}" />
        @endif

        <div>
            <h1 class="font-fraunces text-[30px] font-semibold leading-tight text-ink mb-0.5">{{ $this->recipe->name }}</h1>

            @if (filled($recipe->link))
            <a
                href="{{ $recipe->link }}"
                target="_blank"
                class="inline-flex items-center gap-1 font-manrope text-[13px] font-bold text-forest hover:text-ink">
                {{ __('Otwórz źródło przepisu') }} →
            </a>
            @endif
        </div>

        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('recipes.edit', $this->recipe) }}"
                wire:navigate
                class="flex-1 inline-flex items-center justify-center gap-1.5 bg-ink text-cream rounded-2xl py-[11px] font-manrope text-[13.5px] font-extrabold">
                <flux:icon.pencil-square class="size-4" />
                {{ __('Edytuj') }}
            </a>

            <flux:modal.trigger name="delete-recipe-modal">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 border-[1.5px] border-terracotta text-terracotta-dark rounded-2xl px-4 py-[11px] font-manrope text-[13.5px] font-extrabold">
                    <flux:icon.trash class="size-4" />
                    {{ __('Usuń') }}
                </button>
            </flux:modal.trigger>
        </div>

        <div
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
                onVisible() {
                    if (this.awake && ! this.lock && document.visibilityState === 'visible') {
                        this.acquire();
                    }
                },
            }"
            x-init="document.addEventListener('visibilitychange', onVisible)"
            x-show="supported"
            x-cloak>
            <button
                type="button"
                @click="toggle()"
                class="w-full inline-flex items-center justify-center gap-1.5 rounded-2xl px-4 py-[11px] font-manrope text-[13.5px] font-extrabold border-[1.5px] transition-colors"
                :class="awake ? 'bg-gold/15 border-gold text-gold-dark' : 'border-ink/15 text-ink/50'">
                <flux:icon.sun class="size-4" variant="solid" x-show="awake" x-cloak />
                <flux:icon.moon class="size-4" x-show="! awake" x-cloak />
                <span x-text="awake ? '{{ __('Screen will stay on') }}' : '{{ __('Keep screen on') }}'"></span>
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ($recipe->tags as $tag)
            <x-recipe.tag-badge :tag="$tag" />
            @endforeach
        </div>

        @php
            $hasMeAmounts = $recipe->ingredients->contains(fn ($i) => $i->pivot->amount_me !== null);
            $hasWifeAmounts = $recipe->ingredients->contains(fn ($i) => $i->pivot->amount_wife !== null);
        @endphp

        <div
            x-data="{
                portionsMe: 1,
                portionsWife: 1,
                formatAmount(value) {
                    return parseFloat(value.toFixed(2)).toString();
                },
            }">
            <div class="flex items-center justify-between mb-2.5">
                <x-ui.eyebrow>{{ __('Składniki') }}</x-ui.eyebrow>
                @if ($hasMeAmounts || $hasWifeAmounts)
                <span class="font-manrope text-[11px] font-semibold text-ink/40">{{ __('ilości na porcje') }}</span>
                @endif
            </div>

            @if ($hasMeAmounts || $hasWifeAmounts)
            <div class="bg-white border border-ink/10 rounded-2xl mb-3.5 overflow-hidden">
                @if ($hasMeAmounts)
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center size-8 rounded-full bg-terracotta/15 text-terracotta-dark font-manrope text-sm font-extrabold shrink-0">
                            {{ mb_substr(\App\Models\Recipe::nutritionProfileLabel('me'), 0, 1) }}
                        </span>
                        <span class="font-manrope text-[14.5px] font-semibold text-ink">{{ \App\Models\Recipe::nutritionProfileLabel('me') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="portionsMe = Math.max(1, portionsMe - 1)"
                            class="flex items-center justify-center size-7 rounded-full bg-sand text-ink font-manrope font-bold leading-none">−</button>
                        <span class="font-manrope text-[15px] font-extrabold text-ink w-4 text-center" x-text="portionsMe">1</span>
                        <button
                            type="button"
                            @click="portionsMe = portionsMe + 1"
                            class="flex items-center justify-center size-7 rounded-full bg-sand text-ink font-manrope font-bold leading-none">+</button>
                    </div>
                </div>
                @endif

                @if ($hasMeAmounts && $hasWifeAmounts)
                <div class="border-t border-dashed border-ink/15"></div>
                @endif

                @if ($hasWifeAmounts)
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center size-8 rounded-full bg-sage/15 text-forest font-manrope text-sm font-extrabold shrink-0">
                            {{ mb_substr(\App\Models\Recipe::nutritionProfileLabel('wife'), 0, 1) }}
                        </span>
                        <span class="font-manrope text-[14.5px] font-semibold text-ink">{{ \App\Models\Recipe::nutritionProfileLabel('wife') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="portionsWife = Math.max(1, portionsWife - 1)"
                            class="flex items-center justify-center size-7 rounded-full bg-sand text-ink font-manrope font-bold leading-none">−</button>
                        <span class="font-manrope text-[15px] font-extrabold text-ink w-4 text-center" x-text="portionsWife">1</span>
                        <button
                            type="button"
                            @click="portionsWife = portionsWife + 1"
                            class="flex items-center justify-center size-7 rounded-full bg-sand text-ink font-manrope font-bold leading-none">+</button>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <div class="bg-white border border-ink/10 rounded-2xl px-4">
                @foreach ($recipe->ingredients as $index => $ingredient)
                @php $pivot = $ingredient->pivot; @endphp
                <div @class(['flex items-center justify-between gap-2 py-[11px]', 'border-t border-dashed border-ink/25' => $index > 0])>
                    <span class="font-manrope text-[14.5px] font-semibold text-ink">
                        {{ $ingredient->name }}
                    </span>

                    @if ($pivot && ($pivot->amount_me !== null || $pivot->amount_wife !== null))
                    <div class="flex items-center gap-1.5">
                        @if ($pivot->amount_me !== null)
                        <span
                            class="font-manrope text-[11px] font-extrabold text-terracotta-dark bg-sand px-2.5 py-1 rounded-full whitespace-nowrap"
                            x-text="`${formatAmount({{ $pivot->amount_me }} * portionsMe)} {{ $pivot->unit }}`">{{ $pivot->displayQuantityFor('me') }}</span>
                        @endif
                        @if ($pivot->amount_wife !== null)
                        <span
                            class="font-manrope text-[11px] font-extrabold text-ink/60 bg-ink/8 px-2.5 py-1 rounded-full whitespace-nowrap"
                            x-text="`${formatAmount({{ $pivot->amount_wife }} * portionsWife)} {{ $pivot->unit }}`">{{ $pivot->displayQuantityFor('wife') }}</span>
                        @endif
                    </div>
                    @elseif ($pivot?->quantity)
                    <span class="font-manrope text-[13px] font-extrabold text-terracotta-dark bg-sand px-2.5 py-1 rounded-full whitespace-nowrap">
                        {{ $pivot->quantity }}
                    </span>
                    @endif
                </div>
                @endforeach

                @if (filled($this->recipe->content))
                <div class="font-fraunces italic text-[15px] leading-relaxed text-ink/60 py-4 border-t border-dashed border-ink/25 whitespace-pre-line">
                    {{ $this->recipe->content }}
                </div>
                @endif
            </div>
        </div>

        @if ($recipe->hasAnyNutrition())
        <div>
            <x-ui.eyebrow>{{ __('Wartości odżywcze na porcję') }}</x-ui.eyebrow>
            <x-recipe.nutrition :recipe="$recipe" />
        </div>
        @endif
    </div>

    <flux:modal name="delete-recipe-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88dvh] overflow-y-auto">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Usunąć przepis?') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-sm text-ink/60 -mt-4">
                {{ __('Tej operacji nie można cofnąć.') }}
            </p>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 hover:text-ink px-4 py-2.5">
                        {{ __('Anuluj') }}
                    </button>
                </flux:modal.close>

                <button
                    type="button"
                    wire:click="delete"
                    class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">
                    {{ __('Usuń przepis') }}
                </button>
            </div>
        </div>
    </flux:modal>
</div>