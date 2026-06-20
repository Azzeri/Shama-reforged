<x-layouts::app :title="__('Podgląd przepisu')">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $recipe->name }}</h1>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button as="a" href="{{ route('recipes.edit', $recipe) }}" variant="primary" icon="pencil" size="sm">
                    Edytuj
                </flux:button>
                <flux:button as="a" href="{{ route('recipes.index') }}" variant="ghost" icon="arrow-left" size="sm">
                    Wróć do listy
                </flux:button>
            </div>
        </div>

        <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tagi</h2>

                @if ($recipe->tags->isEmpty())
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Brak tagów.</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($recipe->tags as $tag)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-200">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Składniki</h2>

                @if ($recipe->ingredients->isEmpty())
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Brak składników.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($recipe->ingredients as $ingredient)
                            <li class="flex items-center justify-between rounded-md border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $ingredient->name }}</span>
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $ingredient->pivot?->quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Opis</h2>
                <p class="whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $recipe->content }}</p>
            </section>
        </div>
    </div>
</x-layouts::app>