<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $showBottomNav = request()->routeIs(['recipes.index', 'meals.index', 'shopping-list.index', 'ingredients.index', 'profile.edit', 'security.edit']);
    @endphp
    <body class="min-h-screen bg-cream {{ $showBottomNav ? 'pb-[calc(6rem+env(safe-area-inset-bottom))]' : 'pb-8' }}">
        <div class="max-w-xl mx-auto">
            {{ $slot }}
        </div>

        @if ($showBottomNav)
        <x-bottom-nav />
        @endif

        @persist('toast')
            <flux:toast.group position="top end">
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
