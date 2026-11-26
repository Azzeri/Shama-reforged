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

        $this->dispatch('profile-saved');
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

<section class="w-full space-y-4 lg:max-w-[720px]">
    <x-ui.page-header :title="__('Account')" />

    <div>
        <x-ui.section-label class="mb-1.5">{{ __('Profile') }}</x-ui.section-label>
        <form wire:submit="updateProfileInformation" class="rounded-[20px] border border-line bg-white p-4 space-y-3.5 lg:p-5">
            <div class="space-y-3.5 lg:grid lg:grid-cols-2 lg:gap-4 lg:space-y-0">
            <div>
                <label for="profile-name" class="block mb-1.5 text-[14px] font-medium text-ink">{{ __('First name') }}</label>
                <x-ui.text-input id="profile-name" wire:model="name" class="w-full bg-field-bg" required autocomplete="name" />
                <x-ui.field-error name="name" />
            </div>

            <div>
                <label for="profile-email" class="block mb-1.5 text-[14px] font-medium text-ink">{{ __('Email') }}</label>
                <x-ui.text-input id="profile-email" type="email" wire:model="email" class="w-full bg-field-bg" required autocomplete="email" />
                <x-ui.field-error name="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                <p class="mt-2 text-[13px] text-muted">
                    {{ __('Your email address is unverified.') }}
                    <button type="button" class="font-semibold text-terracotta" wire:click.prevent="resendVerificationNotification">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                <p class="mt-1 text-[13px] font-semibold text-forest">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
                @endif
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" data-test="update-profile-button" class="h-12 rounded-[14px] bg-terracotta px-5 text-[15px] font-semibold text-white">
                    {{ __('Save') }}
                </button>
                <span x-data="{ show: false, timer: null }" x-on:profile-saved.window="show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 2000)"
                    x-show="show" x-transition.opacity x-cloak role="status" class="inline-flex items-center gap-1 text-[14px] font-medium text-forest">
                    <x-icon.check class="size-4" />
                    {{ __('Saved') }}
                </span>
            </div>
        </form>
    </div>

    <div>
        <x-ui.section-label class="mb-1.5">{{ __('Settings') }}</x-ui.section-label>
        <x-ui.card class="divide-y divide-divider">
            <a href="{{ route('security.edit') }}" wire:navigate class="flex items-center gap-3.5 px-4 py-3 min-h-[60px]">
                <svg class="size-5 shrink-0 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                <span class="flex-1 min-w-0">
                    <span class="block text-[16px] font-medium text-ink">{{ __('Security') }}</span>
                    <span class="block text-[13px] text-muted">{{ __('Password and sign-in') }}</span>
                </span>
                <x-ui.chevron-right class="size-4" />
            </a>
            <a href="{{ route('ai-report.edit') }}" wire:navigate class="flex items-center gap-3.5 px-4 py-3 min-h-[60px]">
                <x-icon.sparkle class="size-5 text-ink-soft" />
                <span class="flex-1 min-w-0">
                    <span class="block text-[16px] font-medium text-ink">{{ __('AI meal plan') }}</span>
                    <span class="block text-[13px] text-muted">{{ __('Plan generation preferences') }}</span>
                </span>
                <x-ui.chevron-right class="size-4" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" data-test="logout-button" class="w-full flex items-center gap-3.5 px-4 min-h-14 text-left">
                    <svg class="size-5 shrink-0 text-ink-soft" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                    <span class="text-[16px] font-medium text-ink">{{ __('Log out') }}</span>
                </button>
            </form>
        </x-ui.card>
    </div>

</section>
