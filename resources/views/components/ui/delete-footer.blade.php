@props(['name' => null, 'action', 'canDelete' => true, 'deleteLabel' => null])

{{--
    Sheet/form footer with an inline delete confirmation. "Usuń" (red text)
    on the left, the slot (primary actions) on the right; tapping "Usuń"
    swaps the footer for "Usunąć „X”?" with Nie / Usuń. $action = Livewire
    method call run on confirm.
--}}
<div x-data="{ confirming: false }" {{ $attributes->merge(['class' => 'min-h-12 flex items-center gap-3']) }}>
    <template x-if="! confirming">
        <div class="flex w-full items-center gap-3">
            @if ($canDelete)
            <button type="button" x-on:click="confirming = true" class="h-12 px-1 text-[15px] font-semibold text-terracotta">{{ $deleteLabel ?? __('Delete') }}</button>
            @endif
            <div class="ml-auto flex items-center gap-2">{{ $slot }}</div>
        </div>
    </template>
    <template x-if="confirming">
        <div class="flex w-full items-center gap-2">
            <span class="flex-1 min-w-0 text-[15px] font-semibold text-ink">
                {{ $name !== null ? __('Delete “:name”?', ['name' => $name]) : __('Delete?') }}
            </span>
            <button type="button" x-on:click="confirming = false" class="h-11 rounded-full border border-field bg-surface px-4 text-[14px] font-semibold text-ink">{{ __('No') }}</button>
            <button type="button" wire:click="{{ $action }}" class="h-11 rounded-full bg-terracotta px-4 text-[14px] font-semibold text-white">{{ __('Delete') }}</button>
        </div>
    </template>
</div>
