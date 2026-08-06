<?php

use App\Models\Ingredient;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

new #[Layout('layouts::app')] class extends Component {
    #[Validate('required|string|max:255|unique:ingredients,name')]
    public string $name = '';

    public function render()
    {
        return $this->view()
            ->title(__('New ingredient'));
    }

    public function save(): void
    {
        $this->validate();

        Ingredient::query()->create([
            Ingredient::NAME_COLUMN => trim($this->name),
        ]);

        $this->redirectRoute('ingredients.index', navigate: true);
    }
};
?>

<div>
    <div class="space-y-5">
        <h1 class="font-fraunces text-[26px] font-semibold leading-tight text-ink">{{ __('New ingredient') }}</h1>

        <form wire:submit="save" class="space-y-5 bg-white border border-ink/10 rounded-[18px] p-5 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_10px_24px_rgba(43,33,24,0.07)]">
            <div>
                <x-ui.eyebrow>{{ __('Ingredient name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="name" placeholder="{{ __('e.g. Basmati rice') }}" class="w-full" required autofocus />
                <x-ui.field-error name="name" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('ingredients.index') }}" wire:navigate class="font-manrope text-sm font-extrabold text-forest px-4 py-3">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    + {{ __('Add ingredient') }}
                </button>
            </div>
        </form>
    </div>
</div>
