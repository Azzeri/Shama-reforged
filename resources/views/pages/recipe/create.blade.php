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

    /** Draft from "Wklej link" (kept in the session under this token). */
    #[Url(as: 'import')]
    public ?string $importToken = null;

    /** "Wypełnię ręcznie" after a failed import: the link becomes the source. */
    #[Url(as: 'source')]
    public ?string $source = null;

    public function render()
    {
        return $this->view()->title(__('New recipe'));
    }
};
?>

<div class="space-y-4">
    @if ($noteDate && $noteType)
    <x-ui.sub-header :title="__('New recipe')" :back-href="route('meals.day.edit', $noteDate)" :back-label="__('Day editor')">
        <x-slot:action>
            <div class="hidden lg:flex items-center gap-2">
                <a href="{{ route('meals.day.edit', $noteDate) }}" wire:navigate class="h-11 inline-flex items-center px-4 text-[15px] font-semibold text-ink">{{ __('Cancel') }}</a>
                <button type="submit" form="recipe-form" class="h-11 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white hover:bg-terracotta-dark">{{ $noteDate && $noteType ? __('Save and add to the plan') : __('Add recipe') }}</button>
            </div>
        </x-slot:action>
    </x-ui.sub-header>
    @else
    <x-ui.sub-header :title="__('New recipe')" :back-href="route('recipes.index')" :back-label="__('Recipes')">
        <x-slot:action>
            <div class="hidden lg:flex items-center gap-2">
                <a href="{{ route('recipes.index') }}" wire:navigate class="h-11 inline-flex items-center px-4 text-[15px] font-semibold text-ink">{{ __('Cancel') }}</a>
                <button type="submit" form="recipe-form" class="h-11 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white hover:bg-terracotta-dark">{{ $noteDate && $noteType ? __('Save and add to the plan') : __('Add recipe') }}</button>
            </div>
        </x-slot:action>
    </x-ui.sub-header>
    @endif

    <livewire:recipe.form :note-date="$noteDate" :note-type="$noteType" :import-token="$importToken" :source="$source" />
</div>
