<x-layouts::app :title="__('Edycja przepisu')">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Edytuj: {{ $recipe->name }}</h1>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button as="a" href="{{ route('recipes.show', $recipe) }}" variant="primary" icon="eye" size="sm">
                    Podgląd przepisu
                </flux:button>
                <flux:button as="a" href="{{ route('recipes.index') }}" variant="ghost" icon="arrow-left" size="sm">
                    Wróć do listy
                </flux:button>
            </div>
        </div>

        <form method="POST" action="{{ route('recipes.update', $recipe) }}" class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @method('PUT')
            @include('recipes.partials.form', ['submitLabel' => 'Zapisz zmiany'])
        </form>
    </div>
</x-layouts::app>