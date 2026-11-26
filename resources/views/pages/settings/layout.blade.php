{{-- Sub-pages of the Account screen (security, AI meal plan). --}}
<div class="space-y-4">
    <x-ui.sub-header :title="$heading ?? ''" :back-href="route('profile.edit')" :back-label="__('Account')">
        @if (filled($subheading ?? null))
        <p class="text-[15px] leading-normal text-muted">{{ $subheading }}</p>
        @endif
    </x-ui.sub-header>

    <div class="w-full">
        {{ $slot }}
    </div>
</div>
