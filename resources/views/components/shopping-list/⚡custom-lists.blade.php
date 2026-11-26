<?php

use App\Models\CustomList;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/* "Własne" tab: shopping lists independent of the meal plan (shared by the household). */
new class extends Component {
    public bool $sheetOpen = false;

    public string $name = '';

    #[Computed]
    public function lists(): Collection
    {
        return CustomList::query()->with('items')->latest('updated_at')->get();
    }

    #[On('list-new')]
    public function openNew(): void
    {
        $this->reset('name');
        $this->resetErrorBag();
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function create(): void
    {
        $this->name = trim($this->name);
        $this->validate(['name' => ['required', 'string', 'max:60']], attributes: ['name' => __('list name')]);

        $list = CustomList::query()->create(['name' => $this->name]);

        $this->redirectRoute('shopping-list.custom', $list, navigate: true);
    }
};
?>

<div class="space-y-3">
    @foreach ($this->lists as $customList)
    @php
        $total = $customList->items->count();
        $done = $customList->items->where('is_checked', true)->count();
        $preview = $customList->items->where('is_checked', false)->pluck('name')->implode(', ');
    @endphp
    <a href="{{ route('shopping-list.custom', $customList) }}" wire:navigate wire:key="custom-list-{{ $customList->id }}"
        class="flex items-center gap-3 rounded-[20px] border border-line bg-white px-4 py-3.5">
        <span class="flex-1 min-w-0 space-y-1">
            <span class="flex items-baseline gap-2">
                <span class="font-fraunces text-[20px] font-semibold text-ink truncate">{{ $customList->name }}</span>
                <span class="shrink-0 text-[13px] text-muted">{{ __(':done of :total', ['done' => $done, 'total' => $total]) }}</span>
            </span>
            <span class="block text-[14px] text-muted truncate">{{ $preview !== '' ? $preview : ($total ? __('All bought') : __('Empty list')) }}</span>
            <span class="block h-1 rounded-full bg-sand overflow-hidden">
                <span class="block h-full rounded-full bg-sage" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></span>
            </span>
        </span>
        <x-ui.chevron-right class="size-4" />
    </a>
    @endforeach

    <button type="button" wire:click="openNew" class="w-full flex items-center justify-center gap-2 h-16 rounded-[20px] border border-dashed border-faint text-[15px] font-semibold text-terracotta">
        <x-icon.plus class="size-4" />
        {{ __('New list') }}
    </button>

    <p class="text-[13px] text-muted">{{ __('Custom lists are not tied to the meal plan.') }}</p>

    @if ($sheetOpen)
    <x-ui.sheet :title="__('New list')" close="closeSheet">
        <form id="custom-list-form" wire:submit="create">
            <label for="custom-list-name" class="block text-[11px] font-semibold uppercase tracking-[0.09em] text-muted mb-1.5">{{ __('List name') }}</label>
            <x-ui.text-input id="custom-list-name" wire:model.live="name" placeholder="{{ __('e.g. Hardware store') }}" class="w-full bg-field-bg" maxlength="60" autofocus />
            <x-ui.field-error name="name" />
        </form>
        <x-slot:footer>
            <div class="flex justify-end">
                <button type="submit" form="custom-list-form" @disabled(trim($name) === '') class="h-12 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white disabled:opacity-40">{{ __('Create list') }}</button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>
    @endif
</div>
