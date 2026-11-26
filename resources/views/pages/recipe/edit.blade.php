<?php

use App\Models\Recipe;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public function render()
    {
        return $this->view()->title(__('Edit recipe') . ': ' . $this->recipe->name);
    }
};
?>

<div class="space-y-4">
    <x-ui.sub-header :title="__('Edit recipe')" :back-href="route('recipes.show', $this->recipe)" :back-label="__('Recipe')" />

    <livewire:recipe.form :recipe="$this->recipe" />
</div>
