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
};
?>

<div>
    <div class="space-y-5">
        <x-ui.back-button href="{{ route('recipes.show', $this->recipe) }}" />

        <div>
            <h1 class="font-fraunces text-[30px] font-semibold leading-tight text-ink">{{ $this->recipe->name }}</h1>
        </div>

        <livewire:recipe.form :recipe="$this->recipe" />
    </div>
</div>