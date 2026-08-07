<?php

use App\Models\Ingredient;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

new #[Layout('layouts::app')] class extends Component {
    public ?Ingredient $ingredientToDelete = null;

    public string $search = '';

    public string $newIngredientName = '';

    public ?int $editingIngredientId = null;

    public string $editIngredientName = '';

    public function render()
    {
        return $this->view()
            ->title(__('Ingredients'));
    }

    #[Computed]
    public function ingredients()
    {
        return Ingredient::query()
            ->when($this->search, function ($query) {
                $query->where(Ingredient::NAME_COLUMN, 'like', '%' . $this->search . '%');
            })
            ->orderBy(Ingredient::NAME_COLUMN)
            ->get();
    }

    public function resetNewIngredientForm(): void
    {
        $this->newIngredientName = '';
        $this->resetErrorBag();
    }

    public function createIngredient(): void
    {
        $this->validate([
            'newIngredientName' => ['required', 'string', 'max:255', 'unique:ingredients,name'],
        ]);

        Ingredient::query()->create([
            Ingredient::NAME_COLUMN => trim($this->newIngredientName),
        ]);

        $this->resetNewIngredientForm();
        unset($this->ingredients);
        $this->dispatch('modal-close', name: 'add-ingredient-modal');
    }

    public function editIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::query()->findOrFail($ingredientId);
        $this->editingIngredientId = $ingredientId;
        $this->editIngredientName = $ingredient->name;
        $this->resetErrorBag();
    }

    public function saveIngredient(): void
    {
        $this->validate([
            'editIngredientName' => [
                'required', 'string', 'max:255',
                Rule::unique('ingredients', 'name')->ignore($this->editingIngredientId),
            ],
        ]);

        Ingredient::query()->whereKey($this->editingIngredientId)->update([
            Ingredient::NAME_COLUMN => trim($this->editIngredientName),
        ]);

        $this->editingIngredientId = null;
        unset($this->ingredients);
        $this->dispatch('modal-close', name: 'edit-ingredient-modal');
    }

    public function confirmDelete(Ingredient $ingredient): void
    {
        $this->ingredientToDelete = $ingredient;
    }

    public function delete(): void
    {
        $this->ingredientToDelete?->delete();
        $this->ingredientToDelete = null;
        unset($this->ingredients);
    }
};
?>

<div>
    <div class="space-y-5">
        <div class="flex items-start justify-between">
            <div>
                <h1 class="font-fraunces text-[26px] font-semibold leading-tight text-ink mb-1">{{ __('Ingredients') }}</h1>
                <p class="font-manrope text-[13px] text-ink/60">{{ __('List of all ingredients.') }}</p>
            </div>

            <flux:modal.trigger name="add-ingredient-modal">
                <button
                    type="button"
                    wire:click="resetNewIngredientForm"
                    class="inline-flex items-center rounded-xl bg-ink text-cream px-3.5 py-2.5 font-manrope text-[12.5px] font-extrabold whitespace-nowrap">
                    {{ __('Add ingredient') }}
                </button>
            </flux:modal.trigger>
        </div>

        <x-ui.text-input wire:model.live="search" placeholder="{{ __('Search by ingredient name') }}" class="w-full" />

        <div class="rounded-2xl border border-ink/10 overflow-hidden">
            @forelse ($this->ingredients as $ingredient)
            <div wire:key="ingredient-{{ $ingredient->id }}" class="flex items-center justify-between px-4 py-3.5 bg-white {{ !$loop->last ? 'border-b border-ink/10' : '' }}">
                <span class="font-manrope text-[14.5px] font-semibold text-ink">{{ $ingredient->name }}</span>

                <div class="flex gap-2">
                    <flux:modal.trigger name="edit-ingredient-modal">
                        <button
                            type="button"
                            wire:click="editIngredient({{ $ingredient->id }})"
                            class="flex items-center justify-center w-[30px] h-[30px] rounded-[9px] bg-sand text-ink">
                            <flux:icon.pencil class="size-4" />
                        </button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="delete-ingredient-modal">
                        <button
                            type="button"
                            wire:click="confirmDelete({{ $ingredient->id }})"
                            class="flex items-center justify-center w-[30px] h-[30px] rounded-[9px] bg-terracotta text-white">
                            <flux:icon.trash class="size-4" />
                        </button>
                    </flux:modal.trigger>
                </div>
            </div>
            @empty
            <div class="px-4 py-8 text-center bg-white font-manrope text-sm text-ink/50">
                {{ __('No ingredients yet. Add the first one.') }}
            </div>
            @endforelse
        </div>
    </div>

    <flux:modal name="add-ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88vh] overflow-y-auto">
        <form wire:submit="createIngredient" class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('New ingredient') }}</h3>
                <flux:modal.close>
                    <button type="button" wire:click="resetNewIngredientForm" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Ingredient name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="newIngredientName" placeholder="{{ __('e.g. Basmati rice') }}" class="w-full" required autofocus />
                <x-ui.field-error name="newIngredientName" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <button type="button" wire:click="resetNewIngredientForm" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    + {{ __('Add ingredient') }}
                </button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88vh] overflow-y-auto">
        <form wire:submit="saveIngredient" class="space-y-5">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Edit ingredient') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Ingredient name') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model="editIngredientName" placeholder="{{ __('e.g. Basmati rice') }}" class="w-full" required autofocus />
                <x-ui.field-error name="editIngredientName" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-forest px-4 py-3">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>
                <button type="submit" class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-[14px] px-5 py-3 font-manrope text-sm font-extrabold">
                    {{ __('Save changes') }}
                </button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-ingredient-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-cream! max-h-[88vh] overflow-y-auto">
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-2">
                <h3 class="font-fraunces text-xl font-semibold text-ink">{{ __('Delete ingredient?') }}</h3>
                <flux:modal.close>
                    <button type="button" class="text-ink/25 hover:text-ink/50 p-1.5 text-lg leading-none">✕</button>
                </flux:modal.close>
            </div>
            <p class="font-manrope text-sm text-ink/60 -mt-4">
                {{ __('This action cannot be undone. ":name" will be permanently removed.', ['name' => $this->ingredientToDelete?->name]) }}
            </p>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <button type="button" class="font-manrope text-sm font-extrabold text-ink/60 hover:text-ink px-4 py-2.5">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>

                <flux:modal.close>
                    <button
                        type="button"
                        wire:click="delete"
                        class="bg-terracotta hover:bg-terracotta-dark transition-colors text-white rounded-2xl px-5 py-2.5 font-manrope text-sm font-extrabold">
                        {{ __('Delete ingredient') }}
                    </button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
