{{-- Sub-pages of the Account screen (security, AI meal plan). --}}
<div class="space-y-4">
    <x-ui.back-button href="{{ route('profile.edit') }}" :label="__('Account')" />

    <div>
        <h1 class="font-fraunces text-[28px] font-semibold leading-tight text-ink">{{ $heading ?? '' }}</h1>
        @if (filled($subheading ?? null))
        <p class="mt-1 font-inter text-[14px] text-muted">{{ $subheading }}</p>
        @endif
    </div>

    <div class="w-full">
        {{ $slot }}
    </div>
</div>
