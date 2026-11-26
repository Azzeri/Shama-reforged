<?php

use App\Models\PantryGroup;
use App\Models\PantryItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/*
 * "Zapasy" tab: household products with a Jest / Mało / Brak state.
 * Mało / Brak puts the product on the shopping list ("Zapasy domowe").
 */
new class extends Component {
    public bool $sheetOpen = false;

    /** null = the sheet adds a new product. */
    public ?int $editingId = null;

    public string $name = '';

    /** Existing group id, or null while a new group is typed. */
    public ?int $groupId = null;

    public bool $newGroup = false;

    public string $newGroupName = '';

    /** "To ten sam produkt co składnik" (optional). */
    public ?int $ingredientId = null;

    public string $ingredientSearch = '';

    /** recipe count per ingredient id, for "używany w N przepisach" */
    #[Computed]
    public function recipeCounts(): array
    {
        return \App\Models\RecipeIngredientAssignment::query()
            ->selectRaw('ingredient_id, COUNT(DISTINCT recipe_id) as aggregate')
            ->groupBy('ingredient_id')
            ->pluck('aggregate', 'ingredient_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function usedInLabel(?int $ingredientId): ?string
    {
        return $ingredientId ? trans_choice('used in :count recipe|used in :count recipes', $this->recipeCounts[$ingredientId] ?? 0) : null;
    }

    #[Computed]
    public function linkedIngredient(): ?\App\Models\Ingredient
    {
        return $this->ingredientId ? \App\Models\Ingredient::query()->find($this->ingredientId) : null;
    }

    /** "Masz składnik X. Powiązać?" when the product's name is an existing ingredient. */
    #[Computed]
    public function linkProposal(): ?\App\Models\Ingredient
    {
        if ($this->ingredientId || trim($this->name) === '') {
            return null;
        }

        $matcher = app(\App\Support\Ingredients\IngredientMatcher::class);

        return $matcher->same($this->name) ?? $matcher->sameKey($this->name);
    }

    #[Computed]
    public function ingredientResults(): Collection
    {
        return trim($this->ingredientSearch) === ''
            ? collect()
            : app(\App\Support\Ingredients\IngredientMatcher::class)->suggest($this->ingredientSearch, 6)->pluck('ingredient');
    }

    public function linkIngredient(int $id): void
    {
        $this->ingredientId = \App\Models\Ingredient::query()->whereKey($id)->exists() ? $id : null;
        $this->ingredientSearch = '';
    }

    public function unlinkIngredient(): void
    {
        $this->ingredientId = null;
    }

    #[Computed]
    public function groups(): Collection
    {
        return PantryGroup::query()
            ->with(['items' => fn ($query) => $query->orderBy('name')])
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function toBuyCount(): int
    {
        return PantryItem::query()->toBuy()->count();
    }

    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, PantryItem::STATUSES, true)) {
            return;
        }

        $item = PantryItem::query()->find($id);

        if (! $item) {
            return;
        }

        $item->update(['status' => $status]);

        if ($status !== PantryItem::STATUS_OK) {
            \App\Support\Push\PushNotifier::toOthers(auth()->user(), __('Household supplies'),
                __(':name: “:item” — :status', ['name' => auth()->user()->name, 'item' => $item->name, 'status' => mb_strtolower(PantryItem::statusLabel($status))]),
                route('shopping-list.index'));
        }

        $this->refresh();
    }

    #[On('pantry-new')]
    public function openNew(): void
    {
        $this->resetForm();
        $this->groupId = $this->groups->first()?->id;
        $this->newGroup = $this->groups->isEmpty();
        $this->sheetOpen = true;
    }

    public function openEdit(int $id): void
    {
        $item = PantryItem::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->groupId = $item->pantry_group_id;
        $this->ingredientId = $item->ingredient_id;
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
        $this->resetForm();
    }

    public function chooseGroup(int $id): void
    {
        $this->groupId = $id;
        $this->newGroup = false;
    }

    public function startNewGroup(): void
    {
        $this->newGroup = true;
        $this->groupId = null;
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->newGroupName = trim($this->newGroupName);

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'groupId' => [Rule::requiredIf(! $this->newGroup), 'nullable', Rule::exists('pantry_groups', 'id')],
            'newGroupName' => [Rule::requiredIf($this->newGroup), 'nullable', 'string', 'max:60'],
            'ingredientId' => ['nullable', Rule::exists('ingredients', 'id')],
        ], attributes: [
            'name' => __('name'),
            'groupId' => __('group'),
            'newGroupName' => __('group name'),
        ]);

        $groupId = $this->newGroup
            ? PantryGroup::query()->firstOrCreate(['name' => $this->newGroupName])->id
            : $this->groupId;

        if ($this->editingId) {
            PantryItem::query()->findOrFail($this->editingId)->update(['name' => $this->name, 'pantry_group_id' => $groupId, 'ingredient_id' => $this->ingredientId]);
        } else {
            PantryItem::query()->create(['name' => $this->name, 'pantry_group_id' => $groupId, 'ingredient_id' => $this->ingredientId, 'status' => PantryItem::STATUS_OK]);
        }

        PantryGroup::pruneEmpty();
        $this->closeSheet();
        $this->refresh();
    }

    public function delete(): void
    {
        PantryItem::query()->whereKey($this->editingId)->delete();
        PantryGroup::pruneEmpty();
        $this->closeSheet();
        $this->refresh();
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'groupId', 'newGroup', 'newGroupName', 'ingredientId', 'ingredientSearch');
        $this->resetErrorBag();
    }

    private function refresh(): void
    {
        unset($this->groups, $this->toBuyCount);
        $this->dispatch('pantry-changed');
    }
};
?>

<div class="space-y-4 lg:space-y-0">
    @if ($this->toBuyCount > 0)
    <button type="button" wire:click="$parent.$set('tab', 'list')" class="lg:mb-5 lg:max-w-[560px] w-full flex items-center gap-2 min-h-12 rounded-[14px] bg-sage-light px-3.5 text-left text-[15px] text-forest">
        <span class="flex-1">{{ trans_choice(':count product is on the shopping list|:count products are on the shopping list', $this->toBuyCount) }}</span>
        <x-icon.chevron-right class="size-4" />
    </button>
    @endif

    <div class="space-y-4 lg:space-y-0 lg:columns-3 lg:gap-5">
    @forelse ($this->groups as $group)
    <section wire:key="pantry-group-{{ $group->id }}" class="break-inside-avoid lg:mb-5">
        <x-ui.section-label class="mb-1.5">{{ $group->name }}</x-ui.section-label>
        <x-ui.card class="divide-y divide-divider">
            @foreach ($group->items as $item)
            <div wire:key="pantry-item-{{ $item->id }}" class="flex items-center gap-3 px-3.5 py-2 min-h-14">
                <button type="button" wire:click="openEdit({{ $item->id }})" class="flex-1 min-w-0 text-left py-1">
                    <span class="block text-[15px] text-ink truncate">{{ $item->name }}</span>
                    @if ($item->needsBuying())
                    <span class="block text-[12.5px] text-forest">{{ __('on the shopping list') }}</span>
                    @elseif ($item->ingredient_id)
                    <span class="block text-[12.5px] text-muted">{{ $this->usedInLabel($item->ingredient_id) }}</span>
                    @endif
                </button>
                <div role="radiogroup" aria-label="{{ __('Stock of :name', ['name' => $item->name]) }}" class="flex shrink-0 rounded-[10px] bg-sand p-0.5">
                    @foreach (PantryItem::STATUSES as $status)
                    @php $isOn = $item->status === $status; @endphp
                    <button
                        type="button"
                        role="radio"
                        aria-checked="{{ $isOn ? 'true' : 'false' }}"
                        x-on:click="window.haptic?.()"
                        wire:click="setStatus({{ $item->id }}, '{{ $status }}')"
                        @class([
                            'h-9 w-[46px] rounded-lg text-[13px] font-medium transition-colors',
                            'text-muted' => ! $isOn,
                            'bg-surface text-ink shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $isOn && $status === PantryItem::STATUS_OK,
                            'bg-gold text-ink' => $isOn && $status === PantryItem::STATUS_LOW,
                            'bg-terracotta text-white' => $isOn && $status === PantryItem::STATUS_OUT,
                        ])>{{ PantryItem::statusLabel($status) }}</button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </x-ui.card>
    </section>
    @empty
    <div class="rounded-[20px] border border-dashed border-field px-5 py-10 text-center text-[15px] text-muted">
        {{ __('No products yet. Add things you keep at home — when they run low, they land on the shopping list.') }}
    </div>
    @endforelse
    </div>

    @if ($sheetOpen)
    <x-ui.sheet :title="$editingId ? __('Edit product') : __('New product')" close="closeSheet">
        <form id="pantry-form" wire:submit="save" class="space-y-4">
            <div>
                <label for="pantry-name" class="block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5">{{ __('Name') }}</label>
                <x-ui.text-input id="pantry-name" wire:model.live="name" placeholder="{{ __('e.g. Baking paper') }}" class="w-full bg-field-bg" maxlength="100" />
                <x-ui.field-error name="name" />
            </div>

            <fieldset>
                <legend class="block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5">{{ __('Group') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->groups as $group)
                    <x-ui.chip wire:key="pantry-chip-{{ $group->id }}" :active="! $newGroup && $groupId === $group->id" wire:click="chooseGroup({{ $group->id }})">{{ $group->name }}</x-ui.chip>
                    @endforeach
                    @if (! $newGroup)
                    <button type="button" wire:click="startNewGroup" class="inline-flex h-9 items-center rounded-full border-[1.5px] border-terracotta px-3.5 text-[14px] font-medium text-terracotta">+ {{ __('New group') }}</button>
                    @endif
                </div>
                @if ($newGroup)
                <label for="pantry-new-group" class="sr-only">{{ __('Group name') }}</label>
                <x-ui.text-input id="pantry-new-group" wire:model="newGroupName" placeholder="{{ __('Group name, e.g. Garage') }}" class="mt-2 w-full bg-field-bg" maxlength="60" />
                @endif
                <x-ui.field-error name="groupId" />
                <x-ui.field-error name="newGroupName" />
            </fieldset>

            <div>
                <label for="pantry-ingredient" class="block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5">{{ __('The same product as the ingredient') }} <span class="normal-case tracking-normal font-normal">· {{ __('optional') }}</span></label>
                @if ($linked = $this->linkedIngredient)
                <div class="flex items-center gap-2 rounded-[14px] border border-sage/40 bg-sage-light pl-3.5 pr-1.5 py-2">
                    <span class="flex-1 min-w-0">
                        <span class="block text-[16px] text-ink">{{ $linked->name }}</span>
                        <span class="block text-[13px] text-forest">{{ $this->usedInLabel($linked->id) }}</span>
                    </span>
                    <button type="button" wire:click="unlinkIngredient" class="flex size-10 items-center justify-center text-ink" aria-label="{{ __('Remove the link') }}"><x-icon.x class="size-4" /></button>
                </div>
                @else
                @if ($proposal = $this->linkProposal)
                <div class="mb-2 flex items-center gap-2 rounded-[12px] bg-gold-light px-3 py-2.5 text-[14px] text-gold-dark">
                    <span class="flex-1">{{ __('You have the ingredient :name. Link it?', ['name' => $proposal->name]) }}</span>
                    <button type="button" wire:click="linkIngredient({{ $proposal->id }})" class="font-semibold">{{ __('Link') }}</button>
                </div>
                @endif
                <x-ui.search-input id="pantry-ingredient" wire:model.live.debounce.250ms="ingredientSearch" :placeholder="__('Search ingredient')" bg="field" />
                @if ($this->ingredientResults->isNotEmpty())
                <div class="mt-1 rounded-[14px] border border-line bg-surface divide-y divide-divider overflow-hidden">
                    @foreach ($this->ingredientResults as $result)
                    <button type="button" wire:key="pantry-ingredient-{{ $result->id }}" wire:click="linkIngredient({{ $result->id }})" class="block w-full px-3 py-2 text-left hover:bg-field-bg">
                        <span class="block text-[15px] text-ink">{{ $result->name }}</span>
                        <span class="block text-[12px] text-muted">{{ $this->usedInLabel($result->id) }}</span>
                    </button>
                    @endforeach
                </div>
                @endif
                @endif
            </div>
        </form>

        <x-slot:footer>
            <x-ui.delete-footer :name="$name" action="delete" :can-delete="(bool) $editingId">
                <button type="submit" form="pantry-form" @disabled(trim($name) === '') class="h-12 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white disabled:opacity-40">
                    {{ $editingId ? __('Save') : __('Add product') }}
                </button>
            </x-ui.delete-footer>
        </x-slot:footer>
    </x-ui.sheet>
    @endif
</div>
