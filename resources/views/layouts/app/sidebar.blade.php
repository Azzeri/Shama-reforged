<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $showBottomNav = request()->routeIs(['recipes.index', 'meals.index', 'shopping-list.index', 'ingredients.index', 'profile.edit', 'security.edit', 'ai-report.edit']);
    @endphp
    <body class="min-h-dvh bg-cream font-inter text-ink antialiased pt-[env(safe-area-inset-top)] {{ $showBottomNav ? 'pb-[calc(6rem+env(safe-area-inset-bottom))]' : 'pb-8' }}">
        <div class="max-w-xl mx-auto">
            {{ $slot }}
        </div>

        @if ($showBottomNav)
        @persist('bottom-nav')
        <x-bottom-nav />
        @endpersist
        @endif

        @fluxScripts
    </body>
</html>
