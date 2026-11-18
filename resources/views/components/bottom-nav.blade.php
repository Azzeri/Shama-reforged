@php
$items = [
    ['route' => 'recipes.index', 'paths' => ['/recipes'], 'icon' => 'book-open', 'label' => __('Przepisy')],
    ['route' => 'meals.index', 'paths' => ['/meals'], 'icon' => 'calendar-days', 'label' => __('Plan')],
    ['route' => 'shopping-list.index', 'paths' => ['/shopping-list'], 'icon' => 'shopping-cart', 'label' => __('Zakupy')],
    ['route' => 'ingredients.index', 'paths' => ['/ingredients'], 'icon' => 'archive-box', 'label' => __('Składniki')],
    ['route' => 'profile.edit', 'paths' => ['/settings'], 'icon' => 'user-circle', 'label' => auth()->user()->name],
];

$currentPath = '/' . ltrim(request()->path(), '/');
$isActive = fn (array $paths) => collect($paths)->contains(fn ($p) => $currentPath === $p || str_starts_with($currentPath, $p . '/'));
@endphp

{{--
    Persisted across wire:navigate visits so the bar doesn't get torn down and
    re-painted (backdrop blur flicker) on every tab switch. Because the markup
    survives navigation, the active tab is tracked client-side from the URL;
    the server-rendered data-active only covers the very first paint.
--}}
<nav
    x-data="{
        path: window.location.pathname,
        isActive(paths) {
            return paths.some((p) => this.path === p || this.path.startsWith(p + '/'));
        },
    }"
    x-on:livewire:navigated.window="path = window.location.pathname"
    class="fixed inset-x-0 bottom-0 z-40 bg-white/85 backdrop-blur-xl border-t border-ink/10 select-none"
    style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="max-w-xl mx-auto grid grid-cols-5">
        @foreach ($items as $item)
        <a
            href="{{ route($item['route']) }}"
            wire:navigate
            data-nav-root
            x-on:click="path = new URL($el.href).pathname"
            @if ($isActive($item['paths'])) data-active @endif
            x-bind:data-active="isActive({{ Js::from($item['paths']) }})"
            class="group flex flex-col items-center justify-center gap-0.5 py-2.5 min-w-0 text-ink/40 data-active:text-terracotta-dark">
            <x-dynamic-component :component="'flux::icon.' . $item['icon']" variant="outline" class="size-6 group-data-active:hidden" />
            <x-dynamic-component :component="'flux::icon.' . $item['icon']" variant="solid" class="size-6 hidden group-data-active:block" />
            <span class="font-manrope text-[10.5px] font-bold truncate max-w-full px-1">{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>
</nav>
