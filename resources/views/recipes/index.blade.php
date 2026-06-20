<x-layouts::app :title="__('Przepisy')">
    <div class="mx-auto w-full max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Przepisy</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Kafelki przepisów z filtrowaniem po nazwie i tagach.</p>
            </div>

            <a
                href="{{ route('recipes.create') }}"
                class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
            >
                Dodaj przepis
            </a>
        </div>

        <form
            method="GET"
            action="{{ route('recipes.index') }}"
            data-live-filter-form
            class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto]">
                <flux:input
                    name="q"
                    :label="__('Szukaj po nazwie')"
                    :value="$search"
                    :placeholder="__('Wpisz nazwę przepisu')"
                />

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">Tagi</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tagOptions as $tagOption)
                            <label class="inline-flex items-center gap-2 rounded-full border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700">
                                <input
                                    type="checkbox"
                                    name="tags[]"
                                    value="{{ $tagOption->id }}"
                                    @checked($selectedTagIds->contains((int) $tagOption->id))
                                >
                                <span>{{ $tagOption->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary" icon="funnel">Filtruj</flux:button>
                    <flux:button as="a" href="{{ route('recipes.index') }}" variant="ghost">Wyczyść</flux:button>
                </div>
            </div>
        </form>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($recipes as $recipe)
                <article class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $recipe->name }}</h2>
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            <flux:button as="a" href="{{ route('recipes.show', $recipe) }}" icon="eye" variant="ghost" size="sm">Podgląd</flux:button>
                            <flux:button as="a" href="{{ route('recipes.edit', $recipe) }}" icon="pencil" variant="ghost" size="sm">Edycja</flux:button>
                        </div>
                    </div>

                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ \Illuminate\Support\Str::limit($recipe->content, 120) }}</p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($recipe->tags as $tag)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-200">{{ $tag->name }}</span>
                        @empty
                            <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">bez tagów</span>
                        @endforelse
                    </div>

                    <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                        Składniki: {{ $recipe->ingredients->pluck('name')->join(', ') ?: '-' }}
                    </div>

                    <div class="mt-4 flex justify-end">
                        <form method="POST" action="{{ route('recipes.destroy', $recipe) }}" onsubmit="return confirm('Na pewno usunąć ten przepis?')">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" icon="trash" variant="danger" size="sm" />
                        </form>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
                    Brak przepisów dla wybranych filtrów.
                </div>
            @endforelse
        </div>

        <div>
            {{ $recipes->links() }}
        </div>
    </div>
</x-layouts::app>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('[data-live-filter-form]');
        if (!form) {
            return;
        }

        const textInput = form.querySelector('input[name="q"]');
        const tagInputs = form.querySelectorAll('input[name="tags[]"]');

        if (textInput) {
            textInput.focus();
            const valueLength = textInput.value.length;
            textInput.setSelectionRange(valueLength, valueLength);
        }

        let debounceTimer;
        let isComposing = false;

        const submitForm = () => {
            if (isComposing) {
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                form.requestSubmit();
            }, 300);
        };

        if (textInput) {
            textInput.addEventListener('compositionstart', () => {
                isComposing = true;
            });

            textInput.addEventListener('compositionend', () => {
                isComposing = false;
                submitForm();
            });

            textInput.addEventListener('input', submitForm);
        }

        tagInputs.forEach((checkbox) => {
            checkbox.addEventListener('change', submitForm);
        });
    });
</script>