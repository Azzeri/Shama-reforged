{{-- Ingredient form, shared by the phone's sheet and the desktop side panel. $prefix keeps element ids unique. --}}
<form wire:submit="save" class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-fraunces text-[20px] font-semibold text-ink">{{ $editingId ? __('Edit ingredient') : __('New ingredient') }}</h2>
        @if ($desktop)
        <button type="button" wire:click="closePanel" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
        @else
        <flux:modal.close>
            <button type="button" class="-mr-2.5 flex size-11 items-center justify-center text-ink" aria-label="{{ __('Close') }}"><x-icon.x class="size-[18px]" /></button>
        </flux:modal.close>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}name" class="{{ $label }}">{{ __('Name') }}</label>
        <x-ui.text-input id="{{ $prefix }}name" wire:model.live="name" class="w-full bg-field-bg" maxlength="255" />
        <x-ui.field-error name="name" />
    @if ($similar = $this->similarExisting)
    <div class="mt-2 rounded-[12px] bg-gold-light px-3 py-2.5 text-[14px] text-gold-dark">
        <p>{{ __('Did you mean: :name?', ['name' => $similar->name]) }}</p>
        <div class="mt-1 flex gap-4">
            <button type="button" wire:click="openEdit({{ $similar->id }})" class="font-semibold underline-offset-2 hover:underline">{{ __('Open') }}</button>
            <button type="button" wire:click="$set('similarDismissed', true)" class="font-semibold underline-offset-2 hover:underline">{{ __('Add as new') }}</button>
        </div>
    </div>
    @endif
        @if ($editingId && $this->editingRecipeCount)
        <p class="mt-1 text-[12.5px] text-muted">{{ trans_choice('Used in :count recipe|Used in :count recipes', $this->editingRecipeCount) }}</p>
        @endif
    </div>

    <div>
        <span class="{{ $label }}">{{ __('When to buy') }}</span>
        <div role="radiogroup" class="flex rounded-[14px] bg-sand p-1 gap-1">
            @foreach (\App\Models\Ingredient::PURCHASE_TIMINGS as $timing)
            @php $on = $purchaseTiming === $timing; @endphp
            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('purchaseTiming', '{{ $timing }}')"
                @class(['flex-1 min-w-0 rounded-[10px] px-2 py-1.5 min-h-11 text-[13px] leading-tight', 'bg-white text-ink font-semibold shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $on, 'text-muted font-medium' => ! $on])>
                {{ \App\Models\Ingredient::purchaseTimingLabel($timing) }}
            </button>
            @endforeach
        </div>
    </div>

    <div>
        <label for="{{ $prefix }}category" class="{{ $label }}">{{ __('Store section') }}</label>
        <select id="{{ $prefix }}category" wire:model="category" class="w-full h-11 border border-field bg-field-bg rounded-xl px-3 text-base text-ink">
            @foreach (\App\Models\Ingredient::CATEGORIES as $categoryOption)
            <option value="{{ $categoryOption }}">{{ \App\Models\Ingredient::categoryLabel($categoryOption) }}</option>
            @endforeach
        </select>
    </div>

    <div class="rounded-2xl border border-line px-3.5 py-1">
        <x-ui.toggle wire:model="skipShopping" :label="__('Always at home')" :hint="__('Never added to the shopping list (salt, water...)')" />
    </div>

    <div x-data="{ open: @js($desktop) }" class="rounded-2xl border border-line">
        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center gap-2 px-3.5 min-h-12 text-left">
            <span class="flex-1 text-[14px] font-semibold text-ink">{{ __('Unit weights') }} <span class="font-normal text-muted">· {{ __('optional') }}</span></span>
            @if ($filledWeights)
            <span class="text-[12.5px] text-muted">{{ __('filled in :count', ['count' => $filledWeights]) }}</span>
            @endif
            <x-icon.chevron-down class="size-4 text-muted transition-transform" x-bind:class="open && 'rotate-180'" />
        </button>
        <div x-show="open" x-collapse @class(['border-t border-divider px-3.5 py-2', 'grid grid-cols-2 gap-x-3 gap-y-1.5' => $desktop, 'space-y-1.5' => ! $desktop])>
            @foreach (\App\Models\Ingredient::WEIGHABLE_UNITS as $unit)
            <div wire:key="{{ $prefix }}weight-{{ $unit }}" class="flex items-center gap-2">
                <label for="{{ $prefix }}weight-{{ $unit }}" class="{{ $desktop ? 'w-[4.5rem]' : 'w-24' }} shrink-0 text-[14px] text-ink">1 {{ \App\Support\ShoppingList\Quantity::unitLabel($unit) }} =</label>
                <input id="{{ $prefix }}weight-{{ $unit }}" type="text" inputmode="decimal" wire:model="unitWeights.{{ $unit }}" class="{{ $desktop ? 'w-14' : 'w-24' }} h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-right text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                <span class="text-[14px] text-muted">g</span>
            </div>
            <x-ui.field-error name="unitWeights.{{ $unit }}" />
            @endforeach
        </div>
    </div>

    @if ($editingId)
    <button type="button" wire:click="openMerge({{ $editingId }})" class="inline-flex h-11 items-center gap-1.5 text-[14px] font-semibold text-ink-soft">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3v6a4 4 0 0 0 4 4h8"/><path d="m15 9 4 4-4 4"/><path d="M6 21v-4"/></svg>
        {{ __('Merge with another ingredient…') }}
    </button>
    @endif

    <div class="border-t border-divider pt-3">
        <x-ui.delete-footer :name="$name" action="delete" :can-delete="(bool) $editingId">
            <button type="submit" @disabled(trim($name) === '') class="h-12 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white disabled:opacity-40">
                {{ $editingId ? __('Save') : __('Add ingredient') }}
            </button>
        </x-ui.delete-footer>
    </div>
</form>
