<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] class extends Component {
    public function render()
    {
        return $this->view()
            ->title(__('New recipe'));
    }
};
?>

<div>
    <div class="space-y-4">
        <x-ui.back-button href="{{ route('recipes.index') }}" :label="__('Recipes')" />

        <h1 class="font-fraunces text-[28px] font-semibold leading-tight text-ink">{{ __('New recipe') }}</h1>

        <livewire:recipe.form />
    </div>
</div>
