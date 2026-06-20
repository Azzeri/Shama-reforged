<x-layouts::app :title="__('Podgląd przepisu')">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $recipe->name }}</h1>

            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('recipes.edit', $recipe) }}" class="text-blue-700 hover:underline dark:text-blue-300">Edytuj</a>
                <a href="{{ route('recipes.index') }}" class="text-zinc-700 hover:underline dark:text-zinc-300">Wróć do listy</a>
            </div>
        </div>

        <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
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