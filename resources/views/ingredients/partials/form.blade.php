@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<flux:input
    name="name"
    :label="__('Nazwa składnika')"
    type="text"
    :value="old('name', $ingredient->name ?? '')"
    required
    autofocus
/>

<div class="flex items-center justify-end gap-3">
    <flux:link href="{{ route('ingredients.index') }}">Anuluj</flux:link>
    <flux:button type="submit" variant="primary">{{ $submitLabel }}</flux:button>
</div>