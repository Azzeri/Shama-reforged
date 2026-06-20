<x-layouts::app :title="__('Kalendarz posiłków')">
    <div class="mx-auto w-full max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900 md:flex-row md:items-end md:justify-between">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Kalendarz posiłków</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Jedna strona pokazuje jeden tydzień. Kliknij dzień, aby edytować jego posiłki.</p>
            </div>

            <div class="flex items-center gap-2">
                <flux:button as="a" href="{{ route('meals.index', ['week' => $previousWeek]) }}" icon="chevron-left" variant="ghost" size="sm" />
                <div class="rounded-full border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 dark:border-zinc-700 dark:text-zinc-200">
                    {{ $weekStart->isoFormat('D MMMM') }} - {{ $weekStart->copy()->endOfWeek()->isoFormat('D MMMM YYYY') }}
                </div>
                <flux:button as="a" href="{{ route('meals.index', ['week' => $nextWeek]) }}" icon="chevron-right" variant="ghost" size="sm" />
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-3 md:grid-cols-7">
            @foreach ($days as $dayData)
                @php
                    $isToday = $dayData['date']->isToday();
                    $dayMeals = $dayData['meals'];
                @endphp

                <a
                    href="{{ route('meals.day', $dayData['date']->toDateString()) }}"
                    class="group min-h-56 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $dayData['date']->isoFormat('dddd') }}</div>
                            <div class="mt-1 text-2xl font-semibold {{ $isToday ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $dayData['date']->format('d') }}
                            </div>
                        </div>

                        @if ($isToday)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Dziś</span>
                        @endif
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse ($dayMeals as $meal)
                            <div class="rounded-xl border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ \App\Models\Meal::TYPE_LABELS[$meal->type] ?? ucfirst($meal->type) }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meal->date?->format('H:i') }}</span>
                                </div>
                                <div class="mt-1 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $meal->recipes->pluck('name')->join(', ') ?: 'Brak przepisów' }}
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-zinc-300 px-3 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Brak posiłków
                            </div>
                        @endforelse
                    </div>
                </a>
            @endforeach
        </div>

        <div class="flex items-center justify-end">
            <flux:button as="a" href="{{ route('meals.create', ['date' => now()->format('Y-m-d\TH:i')]) }}" variant="primary" icon="plus">
                Dodaj posiłek
            </flux:button>
        </div>
    </div>
</x-layouts::app>