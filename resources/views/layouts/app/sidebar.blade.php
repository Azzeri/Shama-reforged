<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream dark:bg-zinc-900">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-ink/10 bg-sand dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('meals.index') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden text-ink/50 hover:text-ink" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Shama')" class="grid">
                    <flux:sidebar.item :href="route('ingredients.index')" :current="request()->routeIs('ingredients.*')" wire:navigate class="font-manrope font-bold text-ink/70">
                        {{ __('Ingredients') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('recipes.index')" :current="request()->routeIs('recipes.*')" wire:navigate class="font-manrope font-bold text-ink/70">
                        {{ __('Recipes') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('meals.index')" :current="request()->routeIs('meals.*')" wire:navigate class="font-manrope font-bold text-ink/70">
                        {{ __('Meal plan') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item :href="route('shopping-list.index')" :current="request()->routeIs('shopping-list.*')" wire:navigate class="font-manrope font-bold text-ink/70">
                        {{ __('Shopping list') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden bg-cream border-ink/10">
            <flux:sidebar.toggle class="lg:hidden text-ink/60" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    circle
                    avatar:class="bg-sand! text-ink! font-manrope! font-extrabold!"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    circle
                                    class="bg-sand! text-ink! font-manrope! font-extrabold!"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
