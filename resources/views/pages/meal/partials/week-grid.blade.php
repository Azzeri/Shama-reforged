{{--
    Desktop week grid: a column per day, a row per meal type. The day header
    or a "+" opens the day editor in the side panel; a recipe opens the recipe.
    Scrolls horizontally when the window is too narrow.
--}}
@php
    $types = $this->gridTypes;
@endphp
<div class="overflow-x-auto rounded-[20px] border border-line bg-white">
    <div class="grid min-w-[960px]" style="grid-template-columns: 96px repeat(7, minmax(120px, 1fr))">
        <div class="border-b border-line"></div>
        @foreach ($this->days as $dayData)
        @php
            $dateString = $dayData['date']->toDateString();
            $dayName = \Illuminate\Support\Str::ucfirst($dayData['date']->copy()->locale(app()->getLocale())->isoFormat('dddd'));
            $isOpen = $editingDay === $dateString;
        @endphp
        <button type="button" wire:key="grid-head-{{ $dateString }}" wire:click="openDay('{{ $dateString }}')"
            aria-label="{{ __('Edit day') }}: {{ $dayName }} {{ $dayData['date']->format('j') }}"
            @class([
                'flex items-start gap-2 border-b border-l border-line px-3.5 py-3 text-left',
                'bg-field-bg' => $dayData['isToday'] && ! $isOpen,
                'bg-sand' => $isOpen,
            ])>
            <span class="flex-1 min-w-0">
                <span class="flex items-center gap-1.5">
                    <span class="font-fraunces text-[22px] font-semibold leading-none text-ink tabular-nums">{{ $dayData['date']->format('j') }}</span>
                    @if ($dayData['isToday'])
                    <span class="rounded-full bg-today px-1.5 py-0.5 text-[10.5px] font-semibold text-today-ink">{{ __('Today') }}</span>
                    @endif
                </span>
                <span class="block mt-0.5 text-[13px] font-medium text-ink-soft truncate">{{ $dayName }}</span>
            </span>
            <x-icon.pencil class="mt-1 size-4 text-muted" />
        </button>
        @endforeach

        @foreach ($types as $type)
        <div wire:key="grid-label-{{ $type }}" @class(['px-3.5 py-3 text-[11px] font-semibold uppercase tracking-[0.09em] text-muted', 'border-b border-line' => ! $loop->last])>{{ \App\Models\Meal::typeLabel($type) }}</div>
        @foreach ($this->days as $dayData)
        @php
            $dateString = $dayData['date']->toDateString();
            $meal = $dayData['meals']->firstWhere('type', $type);
        @endphp
        <div wire:key="grid-{{ $dateString }}-{{ $type }}" @class([
            'min-h-[120px] border-l border-line px-3.5 py-2.5 space-y-1.5',
            'border-b' => ! $loop->parent->last,
            'bg-field-bg' => $dayData['isToday'],
            'opacity-60' => $dayData['isPast'],
        ])>
            @if ($meal && ($meal->recipes->isNotEmpty() || filled($meal->note)))
            @foreach ($meal->recipes as $recipe)
            @php $caption = $this->recipeCaption($recipe); @endphp
            <a href="{{ route('recipes.show', $recipe) }}" wire:navigate wire:key="grid-{{ $meal->id }}-{{ $recipe->id }}" class="block text-[14px] leading-snug text-ink hover:text-terracotta">
                {{ $recipe->name }}
                @if ($caption)
                <span class="block text-[12px] text-muted">{{ $caption }}</span>
                @endif
            </a>
            @endforeach
            @if (filled($meal->note))
            <p class="text-[14px] leading-snug italic text-ink-soft">{{ $meal->note }}</p>
            @endif
            @else
            <button type="button" wire:click="openDay('{{ $dateString }}')" aria-label="{{ __('Plan') }}: {{ \App\Models\Meal::typeLabel($type) }}, {{ $dayData['date']->format('j') }}"
                class="flex size-9 items-center justify-center rounded-full border border-dashed border-faint text-terracotta hover:bg-today/40">
                <x-icon.plus class="size-4" />
            </button>
            @endif
        </div>
        @endforeach
        @endforeach
    </div>
</div>
<p class="mt-3 text-[13px] text-muted">{{ __('Click a day to edit it. Click a recipe to open it.') }}</p>
