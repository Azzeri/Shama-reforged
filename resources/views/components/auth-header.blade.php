@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center gap-1">
    <flux:heading size="xl" class="font-fraunces! text-ink!">{{ $title }}</flux:heading>
    <flux:subheading class="font-inter!">{{ $description }}</flux:subheading>
</div>
