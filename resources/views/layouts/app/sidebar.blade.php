<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    @php
        $showBottomNav = request()->routeIs(['recipes.index', 'meals.index', 'shopping-list.index', 'ingredients.index', 'profile.edit', 'security.edit', 'ai-report.edit', 'store-sections.edit']);
    @endphp
    {{-- Phone: bottom nav on the main screens. Desktop (≥ lg): side menu on every screen. --}}
    <body class="min-h-dvh bg-cream font-inter text-ink antialiased pt-[env(safe-area-inset-top)] lg:pt-0 lg:pb-8 lg:pl-[232px] {{ $showBottomNav ? 'pb-[calc(6rem+env(safe-area-inset-bottom))]' : 'pb-8' }}">
        <div class="max-w-xl mx-auto lg:max-w-[1440px] lg:mx-0">
            {{ $slot }}
        </div>

        @persist('side-nav')
        <x-side-nav />
        @endpersist

        @if ($showBottomNav)
        @persist('bottom-nav')
        <x-bottom-nav />
        @endpersist
        @endif

        @fluxScripts
    </body>
</html>
