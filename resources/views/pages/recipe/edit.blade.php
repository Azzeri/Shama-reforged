<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public function render()
    {
        return $this->view()
            ->title(__('Edit recipe') . ': ' . $this->recipe->name);
    }
};
?>

<div>
    <div class="space-y-4">
        <x-ui.back-button href="{{ route('recipes.show', $this->recipe) }}" :label="__('Recipe')" />

        <h1 class="font-fraunces text-[28px] font-semibold leading-tight text-ink">{{ __('Edit recipe') }}</h1>

        <livewire:recipe.form :recipe="$this->recipe" />
    </div>
</div>
