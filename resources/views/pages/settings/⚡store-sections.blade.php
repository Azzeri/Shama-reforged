<?php

use App\Models\Ingredient;
use App\Models\Setting;
use Livewire\Component;

/* Order of the store sections on the shopping list — one for the whole household, saved on every change. */
new class extends Component {
    /** @var list<string> */
    public array $order = [];

    public function mount(): void
    {
        $this->order = Ingredient::sectionOrder();
    }

    public function render()
    {
        return $this->view()->title(__('Store section order'));
    }

    public function isDefault(): bool
    {
        return $this->order === Ingredient::defaultSectionOrder();
    }

    public function move(string $section, int $by): void
    {
        $from = array_search($section, $this->order, true);

        if ($from === false) {
            return;
        }

        $this->place($section, max(0, min(count($this->order) - 1, $from + $by)));
    }

    /** wire:sort handler: $section dropped at $position (0-based). */
    public function place(string $section, int $position): void
    {
        $order = array_values(array_diff($this->order, [$section]));

        if (count($order) === count($this->order)) {
            return;
        }

        array_splice($order, max(0, min(count($order), $position)), 0, [$section]);
        $this->save($order);
    }

    public function restoreDefault(): void
    {
        $this->save(Ingredient::defaultSectionOrder());
    }

    private function save(array $order): void
    {
        $this->order = $order;
        Setting::put(Ingredient::SECTION_ORDER_SETTING, $order === Ingredient::defaultSectionOrder() ? null : $order);
        $this->dispatch('saved');
    }
}; ?>

<section class="w-full lg:max-w-[720px]">
    <x-pages::settings.layout :heading="__('Section order')" :subheading="__('Arrange the sections the way you walk through the store. The shopping list shows them in this order.')">
        <ol wire:sort="place" class="rounded-[20px] border border-line bg-white divide-y divide-divider overflow-hidden">
            @foreach ($order as $index => $section)
            @php $label = Ingredient::sectionLabel($section); @endphp
            <li wire:key="section-{{ $section }}" wire:sort:item="{{ $section }}" class="flex items-center gap-2 bg-white pl-2 pr-1.5 min-h-14">
                <span wire:sort:handle class="flex size-10 cursor-grab items-center justify-center text-faint" aria-hidden="true">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                </span>
                <span class="w-7 text-right text-[14px] text-muted tabular-nums">{{ $index + 1 }}.</span>
                <span class="flex-1 min-w-0 text-[16px] text-ink">{{ $label }}</span>
                <button type="button" wire:click="move('{{ $section }}', -1)" @disabled($loop->first) class="flex size-11 items-center justify-center text-ink disabled:text-field" aria-label="{{ __('Move :name up', ['name' => $label]) }}">
                    <x-icon.chevron-up class="size-5" />
                </button>
                <button type="button" wire:click="move('{{ $section }}', 1)" @disabled($loop->last) class="flex size-11 items-center justify-center text-ink disabled:text-field" aria-label="{{ __('Move :name down', ['name' => $label]) }}">
                    <x-icon.chevron-down class="size-5" />
                </button>
            </li>
            @endforeach
        </ol>

        <div class="mt-4 flex items-center justify-between gap-4">
            <p class="text-[13px] text-muted">{{ __('Changes are saved automatically.') }}</p>
            <button type="button" wire:click="restoreDefault" @disabled($this->isDefault()) class="h-11 rounded-full border border-field bg-white px-4 text-[14px] font-semibold text-ink disabled:text-faint">
                {{ __('Restore the default') }}
            </button>
        </div>
    </x-pages::settings.layout>
</section>
