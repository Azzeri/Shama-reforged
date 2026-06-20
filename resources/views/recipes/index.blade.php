<x-layouts::app :title="__('Przepisy')">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Przepisy</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Lista wszystkich przepisów.</p>
            </div>

            <a
                href="{{ route('recipes.create') }}"
                class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                Dodaj przepis
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">Nazwa</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">Składniki</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-600 dark:text-zinc-300">Akcje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                    @forelse ($recipes as $recipe)
                        <tr>
                            <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $recipe->name }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $recipe->ingredients->pluck('name')->join(', ') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <flux:button as="a" href="{{ route('recipes.show', $recipe) }}" icon="eye" variant="ghost" size="sm" />
                                    <flux:button as="a" href="{{ route('recipes.edit', $recipe) }}" icon="pencil" variant="ghost" size="sm" />

                                    <form method="POST" action="{{ route('recipes.destroy', $recipe) }}" onsubmit="return confirm('Na pewno usunąć ten przepis?')">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" icon="trash" variant="danger" size="sm" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-zinc-600 dark:text-zinc-400">Brak przepisów. Dodaj pierwszy.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $recipes->links() }}
        </div>
    </div>
</x-layouts::app>