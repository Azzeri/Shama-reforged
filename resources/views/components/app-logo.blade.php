@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Shama" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-[9px] bg-terracotta shadow-sm overflow-hidden">
            <x-app-logo-icon class="size-6" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Shama" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-[9px] bg-terracotta shadow-sm overflow-hidden">
            <x-app-logo-icon class="size-6" />
        </x-slot>
    </flux:brand>
@endif
