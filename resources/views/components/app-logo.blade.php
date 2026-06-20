@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Shama" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Shama" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
