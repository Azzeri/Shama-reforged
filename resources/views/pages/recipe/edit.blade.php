<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public function render()
    {
        return $this->view()
            ->title(__("Editing") . ": " . $this->recipe->name);
    }

    public function goBack()
    {
        return $this->redirectIntended(
            default: route('recipes.index'),
            navigate: true
        );
    }
};
?>

<div>
    <div class="space-y-5">
        <div>
            <flux:heading size="xl">{{ $this->recipe->name }}</flux:heading>
        </div>

        <div class="flex gap-1 w-full justify-between">
            <div class="flex gap-1">
                <flux:button
                    variant="primary"
                    icon="eye"
                    size="sm"
                    as="a"
                    href="{{ route('recipes.show', $this->recipe) }}"
                    wire:navigate>
                    {{ __('View') }}
                </flux:button>
            </div>

            <flux:button
                variant="ghost"
                size="sm"
                icon="arrow-left"
                wire:click="goBack">
                {{ __('Go back') }}
            </flux:button>
        </div>

        <livewire:recipe.form :recipe="$this->recipe" />


    </div>