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
    <x-ui.sub-header :title="__('Edit recipe')" :back-href="route('recipes.show', $this->recipe)" :back-label="__('Recipe')">
        <x-slot:action>
            <div class="hidden lg:flex items-center gap-2">
                <a href="{{ route('recipes.show', $this->recipe) }}" wire:navigate class="h-11 inline-flex items-center px-4 text-[15px] font-semibold text-ink">{{ __('Cancel') }}</a>
                <button type="submit" form="recipe-form" class="h-11 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white hover:bg-terracotta-dark">{{ __('Save changes') }}</button>
            </div>
        </x-slot:action>
    </x-ui.sub-header>

    <livewire:recipe.form :recipe="$this->recipe" />
</div>
