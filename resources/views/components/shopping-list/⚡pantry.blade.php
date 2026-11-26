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

        PantryItem::query()->whereKey($id)->update(['status' => $status]);
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
        ], attributes: [
            'name' => __('name'),
            'groupId' => __('group'),
            'newGroupName' => __('group name'),
        ]);

        $groupId = $this->newGroup
            ? PantryGroup::query()->firstOrCreate(['name' => $this->newGroupName])->id
            : $this->groupId;

        if ($this->editingId) {
            PantryItem::query()->findOrFail($this->editingId)->update(['name' => $this->name, 'pantry_group_id' => $groupId]);
        } else {
            PantryItem::query()->create(['name' => $this->name, 'pantry_group_id' => $groupId, 'status' => PantryItem::STATUS_OK]);
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
        $this->reset('editingId', 'name', 'groupId', 'newGroup', 'newGroupName');
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
                    @endif
                </button>
                <div role="radiogroup" aria-label="{{ __('Stock of :name', ['name' => $item->name]) }}" class="flex shrink-0 rounded-[10px] bg-sand p-0.5">
                    @foreach (PantryItem::STATUSES as $status)
                    @php $isOn = $item->status === $status; @endphp
                    <button
                        type="button"
                        role="radio"
                        aria-checked="{{ $isOn ? 'true' : 'false' }}"
                        wire:click="setStatus({{ $item->id }}, '{{ $status }}')"
                        @class([
                            'h-9 w-[46px] rounded-lg text-[13px] font-medium transition-colors',
                            'text-muted' => ! $isOn,
                            'bg-white text-ink shadow-[0_1px_2px_rgba(31,27,22,0.12)]' => $isOn && $status === PantryItem::STATUS_OK,
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
