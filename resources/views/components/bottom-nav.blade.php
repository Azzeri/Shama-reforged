@php
$items = [
    ['route' => 'recipes.index', 'pattern' => 'recipes.*', 'icon' => 'book-open', 'label' => __('Przepisy')],
    ['route' => 'meals.index', 'pattern' => 'meals.*', 'icon' => 'calendar-days', 'label' => __('Plan')],
    ['route' => 'shopping-list.index', 'pattern' => 'shopping-list.*', 'icon' => 'shopping-cart', 'label' => __('Zakupy')],
    ['route' => 'ingredients.index', 'pattern' => 'ingredients.*', 'icon' => 'archive-box', 'label' => __('Składniki')],
    ['route' => 'profile.edit', 'pattern' => ['profile.edit', 'security.edit'], 'icon' => 'user-circle', 'label' => auth()->user()->name],
];
@endphp

<nav
    class="fixed inset-x-0 bottom-0 z-40 bg-white/80 backdrop-blur-xl border-t border-ink/10"
    style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="max-w-xl mx-auto grid grid-cols-5">
        @foreach ($items as $item)
        @php $active = request()->routeIs($item['pattern']); @endphp
        <a
            href="{{ route($item['route']) }}"
            wire:navigate
            class="flex flex-col items-center justify-center gap-0.5 py-2.5 min-w-0">
            <x-dynamic-component
                :component="'flux::icon.' . $item['icon']"
                :variant="$active ? 'solid' : 'outline'"
                @class([
                    'size-6',
                    'text-terracotta-dark' => $active,
                    'text-ink/40' => ! $active,
                ]) />
            <span @class([
                'font-manrope text-[10.5px] font-bold truncate max-w-full px-1',
                'text-terracotta-dark' => $active,
                'text-ink/40' => ! $active,
            ])>{{ $item['label'] }}</span>
        </a>
        @endforeach
    </div>
</nav>
