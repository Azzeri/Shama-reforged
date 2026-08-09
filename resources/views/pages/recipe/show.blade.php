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

        <div class="flex flex-wrap gap-2">
            @foreach ($recipe->tags as $tag)
            <x-recipe.tag-badge :tag="$tag" />
            @endforeach
        </div>

        @if ($recipe->hasAnyNutrition())
        <div>
            <x-ui.eyebrow>{{ __('Wartości odżywcze na porcję') }}</x-ui.eyebrow>
            <x-recipe.nutrition :recipe="$recipe" />
        </div>
        @endif

        <div>
            <x-ui.eyebrow>{{ __('Składniki') }}</x-ui.eyebrow>

            <div>
                @foreach ($recipe->ingredients as $index => $ingredient)
                <div @class(['flex items-baseline py-[11px]', 'border-t border-dashed border-ink/25' => $index > 0])>
                    <span class="font-manrope text-[14.5px] font-semibold text-ink whitespace-nowrap">
                        {{ $ingredient->name }}
                    </span>

                    <span class="flex-1 border-b-2 border-dotted border-ink/25 mx-2 -translate-y-1"></span>

                    @if ($ingredient->pivot?->quantity)
                    <span class="font-manrope text-[13px] font-extrabold text-terracotta-dark bg-sand px-2.5 py-1 rounded-full whitespace-nowrap">
                        {{ $ingredient->pivot->quantity }}
                    </span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        @if (filled($this->recipe->content))
        <div class="font-fraunces italic text-[15px] leading-relaxed text-ink/60 mt-2 pt-4 border-t border-ink/25">
            "{{ $this->recipe->content }}"
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