<x-layouts::app :title="__('Lista zakupów')">
    <div class="mx-auto w-full max-w-7xl space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-100">Lista zakupów</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Jedna, wspólna lista zakupów podzielona na dni tygodnia.</p>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">Dodaj pozycję spoza przepisów</h2>

            <form method="POST" action="{{ route('shopping-list.items.store') }}" class="mt-4 grid gap-3 md:grid-cols-5">
                @csrf

                <flux:input name="name" :label="__('Nazwa produktu')" :value="old('name')" required />
                <flux:input name="quantity" :label="__('Ilość')" :value="old('quantity')" required />

                <flux:select name="week_day" :label="__('Dzień tygodnia')" required>
                    <flux:select.option value="" :selected="empty(old('week_day'))">{{ __('Wybierz dzień') }}</flux:select.option>
                    @foreach ($weekDayLabels as $weekDay => $label)
                        <flux:select.option value="{{ $weekDay }}" :selected="old('week_day') === $weekDay">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input name="notes" :label="__('Notatka (opcjonalnie)')" :value="old('notes')" />

                <div class="flex items-end">
                    <flux:button type="submit" variant="primary" icon="plus" class="w-full">Dodaj</flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">Dodaj pozycję bez dnia tygodnia</h2>

            <form method="POST" action="{{ route('shopping-list.items.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf

                <flux:input name="name" :label="__('Nazwa produktu')" required />
                <flux:input name="quantity" :label="__('Ilość')" required />
                <flux:input name="notes" :label="__('Notatka (opcjonalnie)')" />

                <div class="flex items-end">
                    <flux:button type="submit" variant="ghost" icon="plus" class="w-full">Dodaj bez dnia</flux:button>
                </div>
            </form>
        </div>

        <section class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">Do kupienia</h2>

            @foreach ($weekDayLabels as $weekDay => $label)
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</h3>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" id="active-grid-{{ $weekDay }}">
                        @forelse ($activeItemsByDay[$weekDay] as $item)
                            <form method="POST" action="{{ route('shopping-list.items.toggle', $item) }}" data-toggle-shopping-item>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-full w-full rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $item->quantity }}</div>

                                    @if ($item->notes)
                                        <div class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $item->notes }}</div>
                                    @endif
                                </button>
                            </form>
                        @empty
                            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Brak pozycji
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            <div class="space-y-2">
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Bez przypisanego dnia</h3>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" id="active-grid-unscheduled">
                    @forelse ($activeUnscheduledItems as $item)
                        <form method="POST" action="{{ route('shopping-list.items.toggle', $item) }}" data-toggle-shopping-item>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="h-full w-full rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                                <div class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $item->quantity }}</div>

                                @if ($item->notes)
                                    <div class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $item->notes }}</div>
                                @endif
                            </button>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            Brak pozycji
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-700 dark:text-zinc-200">Kupione</h2>

            @foreach ($weekDayLabels as $weekDay => $label)
                <div class="space-y-2">
                    <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $label }}</h3>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" id="checked-grid-{{ $weekDay }}">
                        @forelse ($checkedItemsByDay[$weekDay] as $item)
                            <form method="POST" action="{{ route('shopping-list.items.toggle', $item) }}" data-toggle-shopping-item>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="h-full w-full rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-left opacity-80 transition hover:opacity-100 dark:border-zinc-700 dark:bg-zinc-800">
                                    <div class="text-base font-semibold text-zinc-600 line-through dark:text-zinc-300">{{ $item->name }}</div>
                                    <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $item->quantity }}</div>

                                    @if ($item->notes)
                                        <div class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300">{{ $item->notes }}</div>
                                    @endif
                                </button>
                            </form>
                        @empty
                            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                Brak pozycji
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach

            <div class="space-y-2">
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Bez przypisanego dnia</h3>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" id="checked-grid-unscheduled">
                    @forelse ($checkedUnscheduledItems as $item)
                        <form method="POST" action="{{ route('shopping-list.items.toggle', $item) }}" data-toggle-shopping-item>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="h-full w-full rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-left opacity-80 transition hover:opacity-100 dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="text-base font-semibold text-zinc-600 line-through dark:text-zinc-300">{{ $item->name }}</div>
                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $item->quantity }}</div>

                                @if ($item->notes)
                                    <div class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-xs text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300">{{ $item->notes }}</div>
                                @endif
                            </button>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            Brak pozycji
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const key = 'shopping-list-scroll-y';
            const saved = sessionStorage.getItem(key);

            if (saved !== null) {
                window.scrollTo(0, Number(saved));
                sessionStorage.removeItem(key);
            }

            document.querySelectorAll('form[data-toggle-shopping-item]').forEach((form) => {
                form.addEventListener('submit', () => {
                    sessionStorage.setItem(key, String(window.scrollY));
                });
            });
        });
    </script>
</x-layouts::app>