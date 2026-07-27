@props(['tags', 'label', 'color' => 'gold'])

<div>
    <x-ui.eyebrow>{{ $label }}</x-ui.eyebrow>
    <div class="flex flex-wrap gap-2">
        @foreach ($tags as $tag)
        <x-ui.pill-checkbox value="{{ $tag->id }}" :color="$color" {{ $attributes }}>
            {{ $tag->name }}
        </x-ui.pill-checkbox>
        @endforeach
    </div>
</div>
