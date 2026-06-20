<x-layouts::app :title="__('Dzień w kalendarzu')">
    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 md:flex-row md:items-end md:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $day->copy()->locale('pl')->isoFormat('dddd, D MMMM') }}</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Zarządzaj posiłkami zbiorczo przez Edytuj cały dzień albo dodaj nowy dla tego dnia.</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <flux:button as="a" href="{{ route('meals.index', ['week' => $day->toDateString()]) }}" variant="ghost" icon="arrow-left" size="sm" class="shrink-0">
                    <span class="hidden sm:inline">Powrót do tygodnia</span>
                </flux:button>
                <flux:button as="a" href="{{ route('meals.day.edit', $day->toDateString()) }}" variant="ghost" icon="pencil-square" size="sm" class="shrink-0">
                    <span class="hidden sm:inline">Edytuj cały dzień</span>
                </flux:button>
                <form method="POST" action="{{ route('shopping-list.generate') }}">
                    @csrf
                    <input type="hidden" name="week_start" value="{{ $day->copy()->startOfWeek()->toDateString() }}">
                    <input type="hidden" name="mode" value="selected-days">
                    <input type="hidden" name="days[]" value="{{ $day->toDateString() }}">
                    <flux:button type="submit" variant="ghost" icon="shopping-cart" size="sm" class="shrink-0">
                        <span class="hidden sm:inline">Dodaj składniki z dnia</span>
                    </flux:button>
                </form>
                <flux:button as="a" href="{{ route('meals.create', ['date' => $day->format('Y-m-d\TH:i')]) }}" variant="primary" icon="plus" size="sm" class="shrink-0">
                    <span>Dodaj posiłek</span>
                </flux:button>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($meals as $meal)
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ \App\Models\Meal::TYPE_LABELS[$meal->type] ?? ucfirst($meal->type) }}</div>

                    <div class="mt-4 space-y-2 text-sm text-zinc-700 dark:text-zinc-300">
                        @forelse ($meal->recipes as $recipe)
                            <div class="rounded-xl border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                                {{ $recipe->name }}
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-300 px-3 py-4 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">Brak przepisów</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 md:col-span-2">
                    Brak posiłków dla tego dnia.
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::app>