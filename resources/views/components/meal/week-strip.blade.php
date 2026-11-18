@props(['day', 'route'])

{{-- Mon–Sun strip of the week $day falls in; each day links to $route for that date. --}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-7 gap-1.5']) }}>
    @foreach (range(0, 6) as $offset)
    @php
        $weekDay = $day->copy()->startOfWeek()->addDays($offset);
        $isSelected = $weekDay->isSameDay($day);
    @endphp
    <a
        href="{{ route($route, $weekDay->toDateString()) }}"
        wire:navigate
        wire:key="week-day-{{ $weekDay->toDateString() }}"
        class="flex flex-col items-center rounded-2xl border py-2 {{ $isSelected ? 'bg-ink border-ink text-white' : 'bg-white border-ink/10 text-ink' }}">
        <span class="font-manrope text-[12px] {{ $isSelected ? 'text-white/80' : 'text-ink/60' }}">{{ $weekDay->copy()->locale(app()->getLocale())->isoFormat('dd') }}</span>
        <span class="font-manrope text-lg font-bold leading-tight {{ $weekDay->isToday() && ! $isSelected ? 'text-forest' : '' }}">{{ $weekDay->format('d') }}</span>
    </a>
    @endforeach
</div>
