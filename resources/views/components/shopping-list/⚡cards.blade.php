<?php

use App\Models\LoyaltyCard;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /** Card shown full screen at the checkout. */
    public ?int $openCardId = null;

    /** null = the form adds a new card. */
    public ?int $editingId = null;

    public string $store = '';

    public string $number = '';

    public string $codeType = LoyaltyCard::CODE_BARCODE;

    /** Symbology reported by the scanner; null = guessed from the number. */
    public ?string $format = null;

    public string $color = 'terracotta';

    public bool $confirmingDelete = false;

    #[Computed]
    public function cards(): Collection
    {
        return LoyaltyCard::query()->orderBy('position')->orderBy('store')->get();
    }

    #[Computed]
    public function openCard(): ?LoyaltyCard
    {
        return $this->openCardId ? LoyaltyCard::query()->find($this->openCardId) : null;
    }

    public function show(int $id): void
    {
        $this->openCardId = $id;
        unset($this->openCard);
    }

    public function close(): void
    {
        $this->openCardId = null;
    }

    #[On('card-new')]
    public function openNew(): void
    {
        $this->resetForm();
        $this->color = array_keys(LoyaltyCard::COLORS)[$this->cards->count() % count(LoyaltyCard::COLORS)];
        $this->dispatch('modal-show', name: 'card-modal');
    }

    public function openEdit(int $id): void
    {
        $card = LoyaltyCard::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $card->id;
        $this->store = $card->store;
        $this->number = $card->number;
        $this->codeType = $card->code_type;
        $this->format = $card->format;
        $this->color = $card->color;

        $this->dispatch('modal-show', name: 'card-modal');
    }

    /** A hand-edited number no longer matches the scanned symbology. */
    public function updatedNumber(): void
    {
        $this->format = null;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'store', 'number', 'codeType', 'format', 'color', 'confirmingDelete');
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->store = trim($this->store);
        $this->number = preg_replace('/\s+/', '', $this->number);

        $this->validate([
            'store' => ['required', 'string', 'max:60'],
            'number' => ['required', 'string', 'max:128'],
            'codeType' => ['required', Rule::in(LoyaltyCard::CODE_TYPES)],
            'format' => ['nullable', Rule::in(LoyaltyCard::FORMATS)],
            'color' => ['required', Rule::in(array_keys(LoyaltyCard::COLORS))],
        ], attributes: [
            'store' => __('store'),
            'number' => __('card number'),
        ]);

        $attributes = [
            'store' => $this->store,
            'number' => $this->number,
            'code_type' => $this->codeType,
            'format' => $this->codeType === LoyaltyCard::CODE_BARCODE ? $this->format : null,
            'color' => $this->color,
        ];

        if ($this->editingId) {
            LoyaltyCard::query()->findOrFail($this->editingId)->update($attributes);
        } else {
            LoyaltyCard::query()->create([...$attributes, 'position' => (int) LoyaltyCard::query()->max('position') + 1]);
        }

        $this->resetForm();
        unset($this->cards, $this->openCard);
        $this->dispatch('modal-close', name: 'card-modal');
        $this->dispatch('cards-changed', count: $this->cards->count());
    }

    public function delete(): void
    {
        LoyaltyCard::query()->whereKey($this->editingId)->delete();

        if ($this->openCardId === $this->editingId) {
            $this->openCardId = null;
        }

        $this->resetForm();
        unset($this->cards, $this->openCard);
        $this->dispatch('modal-close', name: 'card-modal');
        $this->dispatch('cards-changed', count: $this->cards->count());
    }
};
?>

<div>
    <div class="grid grid-cols-2 gap-3">
        @foreach ($this->cards as $card)
        <button
            type="button"
            wire:key="card-{{ $card->id }}"
            wire:click="show({{ $card->id }})"
            class="aspect-[1.58/1] rounded-[18px] p-3.5 flex flex-col justify-between text-left text-white shadow-[0_6px_16px_rgba(43,33,24,0.15)]"
            style="background-color: {{ $card->hex() }}">
            <span class="font-fraunces text-[19px] font-semibold leading-tight line-clamp-2">{{ $card->store }}</span>
            <span class="flex items-center justify-between font-inter text-[13px] tracking-wider text-white/85">
                <span>{{ $card->maskedNumber() }}</span>
                @if ($card->code_type === LoyaltyCard::CODE_QR)
                <flux:icon.qr-code class="size-4" />
                @else
                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 5h1.5v14H3zM6 5h1v14H6zM8.5 5H11v14H8.5zM12.5 5h1v14h-1zM15 5h2v14h-2zM18.5 5h1v14h-1zM20.5 5H21v14h-.5z"/></svg>
                @endif
            </span>
        </button>
        @endforeach

        <button
            type="button"
            wire:click="openNew"
            class="aspect-[1.58/1] rounded-[18px] border border-dashed border-field flex flex-col items-center justify-center gap-1 text-terracotta-dark">
            <flux:icon.plus class="size-6" />
            <span class="font-inter text-[15px] font-semibold">{{ __('Add card') }}</span>
        </button>
    </div>

    <p class="mt-4 flex items-start gap-2 font-inter text-[13px] text-muted">
        <flux:icon.cloud class="size-4 shrink-0 mt-px" />
        {{ __('Cards are saved in the app — you both see them, also after changing phones.') }}
    </p>

    {{-- Full-screen card to show at the checkout --}}
    @if ($card = $this->openCard)
    <div
        wire:key="open-card-{{ $card->id }}-{{ $card->updated_at?->timestamp }}"
        x-data="{
            lock: null,
            async init() {
                window.renderCardCode($refs.code, @js(['number' => $card->number, 'codeType' => $card->code_type, 'format' => $card->format]));
                document.documentElement.classList.add('overflow-hidden');
                try { this.lock = await navigator.wakeLock?.request('screen'); } catch (e) {}
            },
            destroy() {
                document.documentElement.classList.remove('overflow-hidden');
                this.lock?.release();
            },
        }"
        class="fixed inset-0 z-50 bg-cream flex flex-col px-4 pt-[calc(1rem+env(safe-area-inset-top))] pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between">
            <button type="button" wire:click="close" class="p-2 -ml-2 text-ink" aria-label="{{ __('Close') }}">
                <flux:icon.x-mark class="size-6" />
            </button>
            <button type="button" wire:click="openEdit({{ $card->id }})" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2.5 font-inter text-[15px] font-semibold text-ink">
                <flux:icon.pencil class="size-4" />
                {{ __('Edit') }}
            </button>
        </div>

        <div class="flex-1 flex flex-col items-center justify-center gap-5">
            <div class="flex items-center gap-2.5">
                <span class="size-3.5 rounded-full" style="background-color: {{ $card->hex() }}"></span>
                <h2 class="font-fraunces text-[28px] font-semibold text-ink">{{ $card->store }}</h2>
            </div>

            <div class="w-full rounded-[22px] bg-white px-6 py-6 flex flex-col items-center gap-3 shadow-[0_6px_20px_rgba(43,33,24,0.08)]">
                <div x-ref="code" @class(['w-full', 'max-w-[230px] aspect-square [&_svg]:size-full' => $card->code_type === LoyaltyCard::CODE_QR])></div>
                <div class="font-mono text-[20px] tracking-[0.12em] text-ink">{{ $card->groupedNumber() }}</div>
            </div>

            <p class="flex items-center gap-2 font-inter text-[13.5px] text-muted">
                <flux:icon.sun class="size-4" />
                {{ __('The screen stays on while the card is open') }}
            </p>
        </div>
    </div>
    @endif

    {{-- Add / edit sheet --}}
    <flux:modal name="card-modal" variant="flyout" position="bottom" :closable="false" class="rounded-t-[24px] bg-white! max-h-[92dvh] overflow-y-auto">
        <form wire:submit="save" class="space-y-5" x-data="cardScanner">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-fraunces text-[22px] font-semibold text-ink">{{ $editingId ? __('Edit card') : __('New card') }}</h3>
                <flux:modal.close>
                    <button type="button" class="p-1 text-muted" aria-label="{{ __('Close') }}">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </flux:modal.close>
            </div>

            <template x-if="supported">
                <div class="space-y-4">
                    <button type="button" x-on:click="start()" class="w-full inline-flex items-center justify-center gap-2 rounded-[16px] border-[1.5px] border-ink py-3.5 font-inter text-[16px] font-semibold text-ink">
                        <flux:icon.viewfinder-circle class="size-5" />
                        {{ __('Scan the code on the card') }}
                    </button>
                    <p x-show="error === 'denied'" x-cloak class="font-inter text-[13px] text-terracotta-dark">{{ __('No access to the camera — allow it in the browser settings or type the number.') }}</p>
                    <p x-show="error === 'failed'" x-cloak class="font-inter text-[13px] text-terracotta-dark">{{ __('Could not start the camera — type the number instead.') }}</p>
                    <div class="flex items-center gap-3 font-inter text-[13px] text-muted">
                        <span class="h-px flex-1 bg-ink/10"></span>{{ __('or type it in') }}<span class="h-px flex-1 bg-ink/10"></span>
                    </div>
                </div>
            </template>

            <div>
                <x-ui.eyebrow>{{ __('Store') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model.live.debounce.200ms="store" placeholder="{{ __('e.g. Biedronka') }}" class="w-full bg-cream" />
                <x-ui.field-error name="store" />
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Card number') }}</x-ui.eyebrow>
                <x-ui.text-input wire:model.live.debounce.300ms="number" inputmode="text" autocomplete="off" placeholder="{{ __('Digits under the barcode') }}" class="w-full bg-cream font-mono" />
                <x-ui.field-error name="number" />
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Code type') }}</x-ui.eyebrow>
                <div class="grid grid-cols-2 gap-1 rounded-2xl bg-sand p-1">
                    @foreach ([LoyaltyCard::CODE_BARCODE => __('Barcode'), LoyaltyCard::CODE_QR => __('QR code')] as $type => $label)
                    <button type="button" wire:click="$set('codeType', '{{ $type }}')" @class(['rounded-xl py-2.5 font-inter text-[15px] font-semibold text-ink transition-colors', 'bg-white shadow-sm' => $codeType === $type])>
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
            </div>

            <div>
                <x-ui.eyebrow>{{ __('Card colour') }}</x-ui.eyebrow>
                <div class="flex flex-wrap gap-2.5">
                    @foreach (LoyaltyCard::COLORS as $key => $hex)
                    <button
                        type="button"
                        wire:click="$set('color', '{{ $key }}')"
                        aria-label="{{ $key }}"
                        @class(['size-10 rounded-full flex items-center justify-center text-white', 'ring-2 ring-offset-2 ring-ink' => $color === $key])
                        style="background-color: {{ $hex }}">
                        @if ($color === $key)<flux:icon.check variant="mini" class="size-5" />@endif
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-line pt-4">
                @if ($confirmingDelete)
                <div class="flex items-center gap-3">
                    <p class="flex-1 font-inter text-[13.5px] text-muted">{{ __('Delete ":name"? This cannot be undone.', ['name' => $store]) }}</p>
                    <button type="button" wire:click="$set('confirmingDelete', false)" class="font-inter text-[14.5px] font-semibold text-ink px-2 py-2">{{ __('No') }}</button>
                    <button type="button" wire:click="delete" class="rounded-[14px] bg-terracotta px-4 py-2.5 font-inter text-[14.5px] font-semibold text-white">{{ __('Delete') }}</button>
                </div>
                @else
                <div class="flex items-center justify-between gap-3">
                    @if ($editingId)
                    <button type="button" wire:click="$set('confirmingDelete', true)" class="inline-flex items-center gap-1.5 font-inter text-[14.5px] font-semibold text-terracotta-dark">
                        <flux:icon.trash class="size-4" />
                        {{ __('Delete') }}
                    </button>
                    @else
                    <span></span>
                    @endif
                    <button type="submit" @disabled(trim($store) === '' || trim($number) === '') class="rounded-[14px] bg-terracotta px-5 py-3 font-inter text-[15px] font-semibold text-white disabled:opacity-40">
                        {{ $editingId ? __('Save') : __('Add card') }}
                    </button>
                </div>
                @endif
            </div>

            {{-- Camera preview while scanning. A native modal <dialog>, so it opens
                 above the form sheet (itself a modal dialog in the top layer). --}}
            <dialog
                x-ref="scanner"
                x-on:cancel.prevent="stop()"
                class="m-0 p-0 w-screen max-w-none h-dvh max-h-none bg-black backdrop:bg-black">
                <div class="relative h-full w-full flex flex-col">
                    <video x-ref="video" class="flex-1 w-full object-cover" playsinline muted autoplay></video>
                    <div class="pointer-events-none absolute inset-x-8 top-1/2 -translate-y-1/2 h-40 rounded-2xl border-2 border-white/80"></div>
                    <div class="absolute inset-x-0 bottom-0 p-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] text-center space-y-3">
                        <p class="font-inter text-[14px] text-white/80">{{ __('Point the camera at the code on the card') }}</p>
                        <button type="button" x-on:click="stop()" class="rounded-full bg-white px-6 py-3 font-inter text-[15px] font-semibold text-ink">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </dialog>
        </form>
    </flux:modal>
</div>
