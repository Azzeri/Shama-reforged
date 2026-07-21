<?php

use App\Models\Recipe;
use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts::app')] class extends Component {
    public Recipe $recipe;

    public function render()
    {
        return $this->view()
            ->title($this->recipe->name);
    }

    public function goBack()
    {
        return $this->redirectIntended(
            default: route('recipes.index'),
            navigate: true
        );
    }

    public function delete(): void
    {
        $this->recipe->delete();

        $this->redirectRoute('recipes.index', navigate: true);
    }
};
?>

<div>
    <div class="space-y-5">
        <div>
            <flux:heading size="xl">{{ $this->recipe->name }}</flux:heading>

            @if (filled($recipe->link))
            <flux:link as="a" href="{{ $recipe->link }}" target="_blank">{{ __('Open recipe source') }} →</flux:link>
            @endif
        </div>

        <div class="flex gap-1 w-full justify-between">
            <div class="flex gap-1">
                <flux:button
                    variant="primary"
                    icon="pencil-square"
                    size="sm"
                    as="a"
                    href="{{ route('recipes.edit', $this->recipe) }}"
                    wire:navigate>
                    {{ __('Edit') }}
                </flux:button>

                <flux:modal.trigger name="delete-recipe-modal">
                    <flux:button
                        variant="danger"
                        size="sm"
                        icon="trash">
                        {{ __('Delete') }}
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <flux:button
                variant="ghost"
                size="sm"
                icon="arrow-left"
                wire:click="goBack">
                {{ __('Go back') }}
            </flux:button>
        </div>

        <div>
            @foreach ($recipe->tags as $tag)
            <flux:badge rounded color="orange">{{ $tag->name }}</flux:badge>
            @endforeach
        </div>

        <div class="space-y-2">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700/50">
                @foreach ($recipe->ingredients as $ingredient)
                <li class="flex items-center justify-between py-2.5 text-sm">
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">
                        {{ $ingredient->name }}
                    </span>

                    @if ($ingredient->pivot?->quantity)
                    <flux:badge size="sm" variant="subtle">
                        {{ $ingredient->pivot->quantity }}
                    </flux:badge>
                    @endif
                </li>
                @endforeach
            </ul>
        </div>

        <flux:separator />

        <flux:text size="lg">{{ $this->recipe->content }}</flux:text>
    </div>

    <flux:modal name="delete-recipe-modal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete recipe?') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Are you sure you want to delete this recipe? This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="delete">
                    {{ __('Delete recipe') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>