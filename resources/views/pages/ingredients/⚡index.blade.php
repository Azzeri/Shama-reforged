<?php

declare(strict_types=1);

use App\Models\Ingredient;
use App\Views\Traits\WithSorting;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

new #[Title('Lista składników')]
class extends Component {
    use WithSorting;
    use WithPagination;

    public const string DELETE_MODAL_NAME = 'delete-ingredient';

    #[Computed]
    public function ingredients(): LengthAwarePaginator
    {
        return Ingredient::query()
            ->tap(fn($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->paginate(50);
    }

    #[On('deletionRequested')]
    public function deleteIngredient(int $assetId): void
    {
        $ingredient = Ingredient::findOrFail($assetId);
        $ingredient->delete();

        $this->dispatch('modal-close', name: self::DELETE_MODAL_NAME);
        Flux::toast(text: __('Ingredient deleted.'), variant: 'success');
    }

    #[On('ingredient-saved')]
    public function refreshTable(): void
    {
        unset($this->ingredients);
    }

};
?>

<div>
    <flux:modal.trigger name="ingredient-form">
        <flux:button icon="plus" wire:click="$dispatch('open-ingredient-form')">
            Dodaj składnik
        </flux:button>
    </flux:modal.trigger>
    <flux:table :paginate="$this->ingredients">
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$sortBy === 'id'"
                :direction="$sortDirection"
                wire:click="sort('id')"
            >
                ID
            </flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortBy === 'name'"
                :direction="$sortDirection"
                wire:click="sort('name')"
            >
                Nazwa
            </flux:table.column>
            <flux:table.column>
                Akcje
            </flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->ingredients as $ingredient)
                <flux:table.row :key="$ingredient->id">
                    <flux:table.cell>{{ $ingredient->id }}</flux:table.cell>
                    <flux:table.cell>{{ $ingredient->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:modal.trigger name="ingredient-form">
                            <flux:button
                                variant="ghost"
                                size="xs"
                                icon="pencil"
                                wire:click="$dispatch('ingredient-form-requested', { id: {{ $ingredient->id }} })"
                            ></flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger :name="self::DELETE_MODAL_NAME">
                            <flux:button
                                variant="danger"
                                size="xs"
                                icon="trash"
                                wire:click="$dispatch('delete-modal-requested', { id: {{ $ingredient->id }}, label: @js($ingredient->name) })"
                            ></flux:button>
                        </flux:modal.trigger>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:shared.delete-modal :modal-name="self::DELETE_MODAL_NAME"></livewire:shared.delete-modal>
    <livewire:ingredients.form></livewire:ingredients.form>
</div>
