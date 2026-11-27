@php
$items = [
    ['route' => 'recipes.index', 'paths' => ['/recipes'], 'icon' => 'book', 'label' => __('Recipes')],
    ['route' => 'meals.index', 'paths' => ['/meals'], 'icon' => 'calendar', 'label' => __('Plan')],
    ['route' => 'shopping-list.index', 'paths' => ['/shopping-list'], 'icon' => 'cart', 'label' => __('Shopping')],
    ['route' => 'ingredients.index', 'paths' => ['/ingredients'], 'icon' => 'pantry', 'label' => __('Ingredients')],
    ['route' => 'profile.edit', 'paths' => ['/settings'], 'icon' => 'user', 'label' => __('Account')],
];

$currentPath = '/' . ltrim(request()->path(), '/');
$isActive = fn (array $paths) => collect($paths)->contains(fn ($p) => $currentPath === $p || str_starts_with($currentPath, $p . '/'));
@endphp

{{--
    Persisted across wire:navigate visits so the bar doesn't get torn down and
    re-painted on every tab switch. Because the markup survives navigation,
    the active tab is tracked client-side from the URL; the server-rendered
    data-active only covers the very first paint.
--}}
<nav
    aria-label="{{ __('Main navigation') }}"
    x-data="{
        path: window.location.pathname,
        isActive(paths) {
            return paths.some((p) => this.path === p || this.path.startsWith(p + '/'));
        },
    }"
    x-on:livewire:navigated.window="path = window.location.pathname"
    class="lg:hidden fixed inset-x-0 bottom-0 z-40 bg-tabbar border-t border-nav-line select-none"
    style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="max-w-xl mx-auto grid grid-cols-5 gap-0.5 px-1.5 pt-2 pb-1">
        @foreach ($items as $item)
        <a
            href="{{ route($item['route']) }}"
            wire:navigate.hover
            data-nav-root
            x-on:click="path = new URL($el.href).pathname"
            @if ($isActive($item['paths'])) data-active aria-current="page" @endif
            x-bind:data-active="isActive({{ Js::from($item['paths']) }})"
            x-bind:aria-current="isActive({{ Js::from($item['paths']) }}) ? 'page' : null"
            class="flex h-[52px] flex-col items-center justify-center gap-1 min-w-0 rounded-[14px] text-nav-icon font-medium data-active:bg-ink data-active:text-on-ink data-active:font-semibold">
            <x-dynamic-component :component="'icon.' . $item['icon']" class="size-[22px]" />
            <span class="text-[11px] truncate max-w-full px-1">{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>
</nav>
