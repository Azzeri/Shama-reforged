<x-layouts::app :title="__('Edycja przepisu')">
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Edytuj: {{ $recipe->name }}</h1>

            <a href="{{ route('recipes.index') }}" class="text-sm text-zinc-700 hover:underline dark:text-zinc-300">Wróć do listy</a>
        </div>

        <form method="POST" action="{{ route('recipes.update', $recipe) }}" class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @method('PUT')
            @include('recipes.partials.form', ['submitLabel' => 'Zapisz zmiany'])
        </form>
    </div>
</x-layouts::app>