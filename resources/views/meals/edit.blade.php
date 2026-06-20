<x-layouts::app :title="__('Edytuj posiłek')">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Edytuj posiłek</h1>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Zmień termin, typ i przypisane przepisy.</p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <form method="POST" action="{{ route('meals.update', $meal) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('meals.partials.form', ['meal' => $meal, 'submitLabel' => 'Zapisz zmiany', 'prefilledDate' => $meal->date->format('Y-m-d\TH:i')])
            </form>
        </div>
    </div>
</x-layouts::app>