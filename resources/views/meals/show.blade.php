<x-layouts::app :title="__('Podgląd posiłku')">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Podgląd posiłku</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Szczegóły zaplanowanego posiłku.</p>
            </div>

            <div class="flex items-center gap-2">
                <flux:button as="a" href="{{ route('meals.edit', $meal) }}" icon="pencil" variant="ghost" size="sm" />
                <flux:button as="a" href="{{ route('meals.index') }}" variant="ghost" size="sm">Powrót</flux:button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Data</div>
                    <div class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $meal->date?->format('d.m.Y H:i') }}</div>
                </div>

                <div>
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Typ</div>
                    <div class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ \App\Models\Meal::TYPE_LABELS[$meal->type] ?? ucfirst($meal->type) }}</div>
                </div>
            </div>

            <div class="mt-6">
                <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Przepisy</div>
                <ul class="mt-2 space-y-2">
                    @forelse ($meal->recipes as $recipe)
                        <li class="rounded-lg border border-zinc-200 px-4 py-3 text-sm text-zinc-800 dark:border-zinc-700 dark:text-zinc-200">
                            <a href="{{ route('recipes.show', $recipe) }}" class="font-medium hover:underline">{{ $recipe->name }}</a>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-600 dark:text-zinc-400">Brak przypisanych przepisów.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts::app>