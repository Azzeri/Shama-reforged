<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] class extends Component {
    public function render()
    {
        return $this->view()
            ->title(__("New recipe"));
    }
};
?>

<div>
    <div class="space-y-5">
        <x-ui.back-button href="{{ route('recipes.index') }}" />

        <div>
            <h1 class="font-fraunces text-[30px] font-semibold leading-tight text-ink">{{ __('New recipe') }}</h1>
        </div>

        <livewire:recipe.form />
    </div>
</div>