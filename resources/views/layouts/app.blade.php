<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="px-4! pt-4! pb-4! lg:px-8! lg:pt-7!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
