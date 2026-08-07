<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-cream antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-5 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-5">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex aspect-square size-11 items-center justify-center rounded-[13px] bg-terracotta shadow-sm overflow-hidden">
                        <x-app-logo-icon class="size-8" />
                    </span>
                    <span class="font-fraunces text-lg font-semibold text-ink">{{ config('app.name', 'Shama') }}</span>
                </a>
                <div class="bg-white border border-ink/10 rounded-[18px] p-6 shadow-[0_1px_2px_rgba(43,33,24,0.06),0_10px_24px_rgba(43,33,24,0.07)] flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
