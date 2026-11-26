<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public function render()
    {
        return $this->view()->title(__('Account'));
    }

    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('meals.index', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full space-y-5">
    <h1 class="font-fraunces text-[34px] font-semibold leading-none text-ink">{{ __('Account') }}</h1>

    <div>
        <x-ui.eyebrow>{{ __('Profile') }}</x-ui.eyebrow>
        <form wire:submit="updateProfileInformation" class="rounded-[18px] border border-line bg-white p-4 space-y-3.5">
            <div>
                <label for="profile-name" class="block mb-1.5 font-inter text-[14px] font-semibold text-ink">{{ __('First name') }}</label>
                <x-ui.text-input id="profile-name" wire:model="name" class="w-full bg-cream" required autocomplete="name" />
                <x-ui.field-error name="name" />
            </div>

            <div>
                <label for="profile-email" class="block mb-1.5 font-inter text-[14px] font-semibold text-ink">{{ __('Email') }}</label>
                <x-ui.text-input id="profile-email" type="email" wire:model="email" class="w-full bg-cream" required autocomplete="email" />
                <x-ui.field-error name="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                <p class="mt-2 font-inter text-[13px] text-muted">
                    {{ __('Your email address is unverified.') }}
                    <button type="button" class="font-semibold text-terracotta-dark" wire:click.prevent="resendVerificationNotification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                <p class="mt-1 font-inter text-[13px] font-semibold text-forest">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
                @endif
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <button type="submit" data-test="update-profile-button" class="rounded-[14px] bg-terracotta px-5 py-3 font-inter text-[15px] font-semibold text-white">
                {{ __('Save') }}
            </button>
        </form>
    </div>

    <div>
        <x-ui.eyebrow>{{ __('Settings') }}</x-ui.eyebrow>
        <div class="rounded-[18px] border border-line bg-white divide-y divide-divider overflow-hidden">
            <a href="{{ route('security.edit') }}" wire:navigate class="flex items-center gap-3.5 px-4 py-3.5">
                <flux:icon.lock-closed class="size-5 shrink-0 text-muted" />
                <span class="flex-1 min-w-0">
                    <span class="block font-inter text-[16px] font-medium text-ink">{{ __('Security') }}</span>
                    <span class="block font-inter text-[13px] text-muted">{{ __('Password and sign-in') }}</span>
                </span>
                <flux:icon.chevron-right class="size-4 shrink-0 text-ink/35" />
            </a>
            <a href="{{ route('ai-report.edit') }}" wire:navigate class="flex items-center gap-3.5 px-4 py-3.5">
                <flux:icon.sparkles class="size-5 shrink-0 text-muted" />
                <span class="flex-1 min-w-0">
                    <span class="block font-inter text-[16px] font-medium text-ink">{{ __('AI meal plan') }}</span>
                    <span class="block font-inter text-[13px] text-muted">{{ __('Report for an AI assistant and plan import') }}</span>
                </span>
                <flux:icon.chevron-right class="size-4 shrink-0 text-ink/35" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" data-test="logout-button" class="w-full flex items-center gap-3.5 px-4 py-3.5 text-left">
                    <flux:icon.arrow-right-start-on-rectangle class="size-5 shrink-0 text-muted" />
                    <span class="font-inter text-[16px] font-medium text-ink">{{ __('Log out') }}</span>
                </button>
            </form>
        </div>
    </div>

</section>
