<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    /** Set when the recipe is created from a note in the day editor. */
    #[Url(as: 'note_date')]
    public ?string $noteDate = null;

    #[Url(as: 'note_type')]
    public ?string $noteType = null;

    public function render()
    {
        return $this->view()->title(__('New recipe'));
    }
};
?>

<div class="space-y-4">
    @if ($noteDate && $noteType)
    <x-ui.sub-header :title="__('New recipe')" :back-href="route('meals.day.edit', $noteDate)" :back-label="__('Day editor')" />
    @else
    <x-ui.sub-header :title="__('New recipe')" :back-href="route('recipes.index')" :back-label="__('Recipes')" />
    @endif

    <livewire:recipe.form :note-date="$noteDate" :note-type="$noteType" />
</div>
