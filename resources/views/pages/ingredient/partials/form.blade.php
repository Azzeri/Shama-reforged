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
                @class(['flex-1 min-w-0 rounded-[10px] px-2 py-1.5 min-h-11 text-[13px] leading-tight', 'bg-surface text-ink font-semibold shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $on, 'text-muted font-medium' => ! $on])>
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

    {{-- Semi-product: link the ingredient to the recipe that makes it. --}}
    <div class="space-y-1.5">
        <label for="{{ $prefix }}semi-recipe" class="{{ $label }} mb-0!">{{ __('Recipe for this ingredient') }} <span class="normal-case tracking-normal font-medium">· {{ __('optional') }}</span></label>
        {{--
            Recipe picker: type to search (any word order, Polish letters optional),
            results show right under the field. The chosen recipe is a row with
            "Change" and a remove button.
        --}}
        @php $semiRecipe = $semiRecipeId ? $this->semiRecipeOptions->firstWhere('id', $semiRecipeId) : null; @endphp
        <div wire:key="{{ $prefix }}semi-picker-{{ $editingId ?? 'new' }}-{{ $semiRecipeId ?? 'none' }}"
            x-data="{
                picking: @js($semiRecipe === null),
                open: false,
                query: '',
                active: 0,
                options: @js($this->semiRecipeOptions->map(fn ($option) => ['id' => $option->id, 'name' => $option->name])->values()),
                fold(text) {
                    return text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ł/g, 'l');
                },
                get results() {
                    const words = this.fold(this.query).split(/\s+/).filter(Boolean);
                    if (! words.length) return this.options;
                    const first = words[0];
                    return this.options
                        .map((option) => ({ option, name: this.fold(option.name) }))
                        .filter(({ name }) => words.every((word) => name.includes(word)))
                        .sort((a, b) => (a.name.startsWith(first) ? 0 : 1) - (b.name.startsWith(first) ? 0 : 1))
                        .map(({ option }) => option);
                },
                choose(option) {
                    if (! option) return;
                    this.open = false;
                    $wire.set('semiRecipeId', option.id);
                },
                move(step) {
                    this.open = true;
                    this.active = Math.max(0, Math.min(this.results.length - 1, this.active + step));
                    this.$nextTick(() => this.$refs.list?.querySelector('[aria-selected=true]')?.scrollIntoView({ block: 'nearest' }));
                },
            }"
            x-on:click.outside="open = false">
            <div x-show="! picking" class="flex items-center gap-1 rounded-xl border border-field bg-field-bg pl-3 min-h-11">
                <span class="flex-1 min-w-0 py-2 text-[15px] font-medium leading-tight text-ink">{{ $semiRecipe?->name }}</span>
                <button type="button" x-on:click="picking = true; open = true; $nextTick(() => $refs.search.focus())" class="h-11 px-2 text-[13px] font-semibold text-terracotta">{{ __('Change') }}</button>
                <button type="button" wire:click="$set('semiRecipeId', null)" class="flex size-11 shrink-0 items-center justify-center text-muted" aria-label="{{ __('Unlink the recipe') }}">
                    <x-icon.x class="size-4" />
                </button>
            </div>

            <div x-show="picking" x-cloak>
                <div class="relative">
                    <x-icon.search class="absolute left-3 top-1/2 -translate-y-1/2 size-[18px] text-muted pointer-events-none" />
                    <input id="{{ $prefix }}semi-recipe" x-ref="search" type="search" x-model="query" autocomplete="off" enterkeyhint="search"
                        role="combobox" aria-autocomplete="list" aria-controls="{{ $prefix }}semi-results" x-bind:aria-expanded="open"
                        placeholder="{{ __('Search recipe') }}"
                        x-on:focus="open = true"
                        x-on:input="open = true; active = 0"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="open && choose(results[active])"
                        x-on:keydown.escape.stop="open = false"
                        class="w-full h-11 border border-field bg-field-bg rounded-xl pl-10 pr-3 text-base text-ink placeholder:text-muted focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
                </div>
                <div x-show="open" id="{{ $prefix }}semi-results" x-ref="list" role="listbox" aria-label="{{ __('Recipes') }}"
                    class="mt-1.5 max-h-[232px] overflow-y-auto overscroll-contain rounded-xl border border-line bg-surface divide-y divide-divider">
                    <template x-for="(option, index) in results" x-bind:key="option.id">
                        <button type="button" role="option" x-bind:aria-selected="index === active" x-on:click="choose(option)" x-on:mousemove="active = index"
                            class="flex w-full items-center min-h-11 px-3 py-2 text-left text-[15px] leading-tight text-ink aria-selected:bg-selected" x-text="option.name"></button>
                    </template>
                    <p x-show="! results.length" class="px-3 py-3 text-[14px] text-muted">{{ __('No recipe with that name.') }}</p>
                </div>
                @if ($semiRecipe)
                <button type="button" x-on:click="picking = false; open = false; query = ''" class="mt-1 h-10 text-[13px] font-semibold text-ink-soft">{{ __('Cancel') }}</button>
                @endif
            </div>
        </div>
        <x-ui.field-error name="semiRecipeId" />
        @if ($semiRecipeId)
        <div role="radiogroup" aria-label="{{ __('Usually') }}" class="grid grid-cols-2 gap-1 rounded-[14px] bg-sand p-1">
            @foreach ([[false, __('I usually buy it ready')], [true, __('I usually make it myself')]] as [$value, $text])
            @php $on = $homemadeDefault === $value; @endphp
            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('homemadeDefault', {{ $value ? 'true' : 'false' }})"
                @class(['min-h-11 rounded-[10px] px-2 text-[13px] leading-tight', 'bg-surface text-ink font-semibold shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $on, 'text-ink-soft font-medium' => ! $on])>{{ $text }}</button>
            @endforeach
        </div>
        <div class="flex items-center gap-2 pt-1">
            <label for="{{ $prefix }}yield" class="flex-1 text-[14px] text-ink">{{ __('The recipe makes') }}</label>
            <input id="{{ $prefix }}yield" type="text" inputmode="decimal" wire:model="yieldAmount" placeholder="{{ __('e.g. 1500') }}" class="w-24 h-10 rounded-[10px] border border-field bg-field-bg px-2.5 text-right text-base text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink" />
            <select wire:model="yieldUnit" aria-label="{{ __('Unit') }}" class="h-10 rounded-[10px] border border-field bg-field-bg px-2 text-base text-ink">
                @foreach (\App\Models\RecipeIngredientAssignment::UNITS as $unitOption)
                <option value="{{ $unitOption }}">{{ \App\Support\ShoppingList\Quantity::unitLabel($unitOption) }}</option>
                @endforeach
            </select>
        </div>
        <x-ui.field-error name="yieldAmount" />
        <p class="text-[12px] leading-normal text-muted">{{ __('This is a semi-product. When adding a recipe to the plan you can change this choice for that day. With the amount the recipe makes filled in, its ingredients are scaled to what a dish needs; without it the whole recipe goes on the shopping list.') }}</p>
        @else
        <p class="text-[12px] leading-normal text-muted">{{ __('If this ingredient can be made at home (e.g. stock), link it to a recipe. When planning you choose: ready or home-made.') }}</p>
        @endif
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
