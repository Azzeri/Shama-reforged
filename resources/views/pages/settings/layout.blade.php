<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')">{{ __('Security') }}</flux:navlist.item>
        </flux:navlist>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button
                type="submit"
                data-test="logout-button"
                class="inline-flex items-center gap-1.5 font-manrope text-sm font-extrabold text-terracotta-dark">
                <flux:icon.arrow-right-start-on-rectangle class="size-4" />
                {{ __('Wyloguj') }}
            </button>
        </form>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
