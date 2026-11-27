@php
$items = [
    ['route' => 'meals.index', 'paths' => ['/meals'], 'icon' => 'calendar', 'label' => __('Plan')],
    ['route' => 'recipes.index', 'paths' => ['/recipes'], 'icon' => 'book', 'label' => __('Recipes')],
    ['route' => 'shopping-list.index', 'paths' => ['/shopping-list'], 'icon' => 'cart', 'label' => __('Shopping')],
    ['route' => 'ingredients.index', 'paths' => ['/ingredients'], 'icon' => 'pantry', 'label' => __('Ingredients')],
];
$account = ['route' => 'profile.edit', 'paths' => ['/settings'], 'icon' => 'user', 'label' => __('Account')];

$currentPath = '/' . ltrim(request()->path(), '/');
$isActive = fn (array $paths) => collect($paths)->contains(fn ($p) => $currentPath === $p || str_starts_with($currentPath, $p . '/'));
@endphp

{{--
    Desktop (≥ lg) side menu; replaces the bottom nav. Persisted across
    wire:navigate visits like the bottom nav, so the active item is tracked
    client-side from the URL.
--}}
<aside
    aria-label="{{ __('Main navigation') }}"
    x-data="{
        path: window.location.pathname,
        isActive(paths) {
            return paths.some((p) => this.path === p || this.path.startsWith(p + '/'));
        },
    }"
    x-on:livewire:navigated.window="path = window.location.pathname"
    class="hidden lg:flex fixed inset-y-0 left-0 z-40 w-[232px] flex-col border-r border-nav-line bg-nav px-3.5 pt-6 pb-5 select-none">
    <a href="{{ route('meals.index') }}" wire:navigate data-nav-root class="mb-5 flex items-center gap-2.5 px-2">
        <span class="flex size-8 items-center justify-center rounded-[9px] bg-terracotta overflow-hidden"><x-app-logo-icon class="size-6" /></span>
        <span class="font-fraunces text-[22px] font-semibold text-ink">Shama</span>
    </a>

    @foreach ([...$items, null, $account] as $item)
    @if ($item === null)
    <div class="flex-1"></div>
    @continue
    @endif
    <a
        href="{{ route($item['route']) }}"
        wire:navigate.hover
        data-nav-root
        x-on:click="path = new URL($el.href).pathname"
        @if ($isActive($item['paths'])) data-active aria-current="page" @endif
        x-bind:data-active="isActive({{ Js::from($item['paths']) }})"
        x-bind:aria-current="isActive({{ Js::from($item['paths']) }}) ? 'page' : null"
        class="group flex h-11 items-center gap-3 rounded-xl px-3 text-[15px] font-medium text-ink hover:bg-ink/5 data-active:bg-ink data-active:text-on-ink data-active:font-semibold">
        <x-dynamic-component :component="'icon.' . $item['icon']" class="size-5 text-nav-icon group-data-active:text-on-ink" />
        {{ $item['label'] }}
    </a>
    @endforeach
</aside>
