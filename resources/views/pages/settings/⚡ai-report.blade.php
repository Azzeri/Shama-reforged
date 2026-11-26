<?php

use App\Actions\MealPlan\BuildMealPlanReport;
use App\Actions\MealPlan\ImportMealPlan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $historyFrom = '';

    public string $historyTo = '';

    public string $planFrom = '';

    public int $planDays = 7;

    public string $ingredientsToUse = '';

    public string $extraWishes = '';

    public string $report = '';

    public string $importJson = '';

    public ?array $importResult = null;

    public function render()
    {
        return $this->view()->title(__('AI meal plan'));
    }

    public function mount(): void
    {
        $this->historyFrom = now()->subDays(28)->toDateString();
        $this->historyTo = now()->toDateString();
        $this->planFrom = now()->addDay()->toDateString();
    }

    #[Computed]
    public function historyLabel(): string
    {
        return $this->rangeLabel($this->historyFrom, $this->historyTo);
    }

    #[Computed]
    public function planLabel(): string
    {
        $from = Carbon::parse($this->planFrom ?: now()->addDay());

        return $this->rangeLabel($from, $from->copy()->addDays(max(1, $this->planDays) - 1));
    }

    private function rangeLabel($from, $to): string
    {
        $locale = app()->getLocale();
        $from = Carbon::parse($from)->locale($locale);
        $to = Carbon::parse($to)->locale($locale);

        if ($from->isSameDay($to)) {
            return $to->isoFormat('D MMMM');
        }

        return $from->isSameMonth($to)
            ? $from->format('j') . ' – ' . $to->isoFormat('D MMMM')
            : $from->isoFormat('D MMMM') . ' – ' . $to->isoFormat('D MMMM');
    }

    public function changePlanDays(int $delta): void
    {
        $this->planDays = max(1, min(31, $this->planDays + $delta));
    }

    /** Builds the report; returned so the page can copy it to the clipboard. */
    public function generate(BuildMealPlanReport $build): string
    {
        $this->validate([
            'historyFrom' => ['required', 'date'],
            'historyTo' => ['required', 'date', 'after_or_equal:historyFrom'],
            'planFrom' => ['required', 'date'],
            'planDays' => ['required', 'integer', 'min:1', 'max:31'],
            'ingredientsToUse' => ['nullable', 'string', 'max:1000'],
            'extraWishes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->report = $build(
            Carbon::parse($this->historyFrom),
            Carbon::parse($this->historyTo),
            Carbon::parse($this->planFrom),
            $this->planDays,
            $this->ingredientsToUse,
            $this->extraWishes,
        );

        return $this->report;
    }

    public function import(ImportMealPlan $import): void
    {
        $this->importResult = null;

        // Accept the AI's whole answer: take the ```json block if present.
        $json = preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $this->importJson, $m) ? $m[1] : trim($this->importJson);
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            $this->addError('importJson', __('This is not valid JSON.'));

            return;
        }

        try {
            $this->importResult = $import($payload);
        } catch (ValidationException $e) {
            $this->addError('importJson', collect($e->errors())->flatten()->take(5)->implode(' '));

            return;
        }

        $this->importJson = '';
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('AI meal plan')" :subheading="__('Generate a report for an AI assistant, then paste its answer back here.')">
        <div class="space-y-4">
            {{-- 1. Report --}}
            <div
                x-data="{
                    copied: false,
                    failed: false,
                    busy: false,
                    async generate() {
                        this.copied = false;
                        this.failed = false;
                        this.busy = true;
                        const report = $wire.generate().then((text) => {
                            if (! text) throw new Error('validation');
                            return text;
                        });
                        try {
                            // iOS only allows clipboard writes inside the tap
                            // itself, so hand it a promise of the text.
                            if (window.ClipboardItem && navigator.clipboard?.write) {
                                await navigator.clipboard.write([new ClipboardItem({
                                    'text/plain': report.then((text) => new Blob([text], { type: 'text/plain' })),
                                })]);
                            } else {
                                await navigator.clipboard.writeText(await report);
                            }
                            this.copied = true;
                        } catch (e) {
                            this.failed = await report.then(() => true, () => false);
                        }
                        this.busy = false;
                    },
                }"
                class="rounded-[22px] border border-line bg-white p-4 space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center size-7 rounded-full bg-ink font-inter text-[14px] font-semibold text-white">1</span>
                    <h2 class="font-inter text-[17px] font-semibold text-ink">{{ __('Report for the AI') }}</h2>
                </div>

                <div class="rounded-2xl border border-line bg-cream divide-y divide-divider" x-data="{ editHistory: false, editPlan: false }">
                    <div class="px-3.5 py-3">
                        <button type="button" x-on:click="editHistory = ! editHistory" class="w-full flex items-center gap-3 text-left">
                            <span class="flex-1">
                                <span class="block font-inter text-[13px] text-muted">{{ __('Based on the history') }}</span>
                                <span class="block font-inter text-[16px] font-semibold text-ink">{{ $this->historyLabel }}</span>
                            </span>
                            <flux:icon.calendar class="size-5 text-muted" />
                        </button>
                        <div x-show="editHistory" x-collapse class="grid grid-cols-2 gap-2 pt-3">
                            <input type="date" wire:model.live="historyFrom" aria-label="{{ __('History from') }}" class="w-full rounded-xl border border-line bg-white px-2.5 py-2 font-inter text-base text-ink" />
                            <input type="date" wire:model.live="historyTo" aria-label="{{ __('History to') }}" class="w-full rounded-xl border border-line bg-white px-2.5 py-2 font-inter text-base text-ink" />
                        </div>
                        <x-ui.field-error name="historyTo" />
                    </div>

                    <div class="px-3.5 py-3">
                        <div class="flex items-center gap-3">
                            <button type="button" x-on:click="editPlan = ! editPlan" class="flex-1 text-left">
                                <span class="block font-inter text-[13px] text-muted">{{ __('Plan') }}</span>
                                <span class="block font-inter text-[16px] font-semibold text-ink">{{ $this->planLabel }}</span>
                            </button>
                            <div class="flex items-center gap-1 rounded-full border border-line bg-white px-1.5 py-1">
                                <button type="button" wire:click="changePlanDays(-1)" class="size-8 font-inter text-lg text-muted" aria-label="{{ __('Fewer days') }}">−</button>
                                <span class="min-w-[52px] text-center font-inter text-[15px] font-semibold text-ink">{{ trans_choice(':count day|:count days', $planDays) }}</span>
                                <button type="button" wire:click="changePlanDays(1)" class="size-8 font-inter text-lg text-terracotta-dark" aria-label="{{ __('More days') }}">+</button>
                            </div>
                        </div>
                        <div x-show="editPlan" x-collapse class="pt-3">
                            <input type="date" wire:model.live="planFrom" aria-label="{{ __('Plan from') }}" class="w-full rounded-xl border border-line bg-white px-2.5 py-2 font-inter text-base text-ink" />
                        </div>
                    </div>
                </div>

                <div>
                    <x-ui.eyebrow optional class="normal-case! tracking-normal! text-[14px]! font-semibold! text-ink! mb-1.5!">{{ __('Ingredients to use') }}</x-ui.eyebrow>
                    <x-ui.textarea wire:model="ingredientsToUse" rows="2" placeholder="{{ __('e.g. zucchini, feta, half a chicken breast') }}" />
                </div>

                <div>
                    <x-ui.eyebrow optional class="normal-case! tracking-normal! text-[14px]! font-semibold! text-ink! mb-1.5!">{{ __('Other wishes') }}</x-ui.eyebrow>
                    <x-ui.textarea wire:model="extraWishes" rows="2" placeholder="{{ __('e.g. fish twice, quick dinners on weekdays') }}" />
                </div>

                <button
                    type="button"
                    x-on:click="generate()"
                    x-bind:disabled="busy"
                    class="w-full rounded-[16px] bg-terracotta py-4 font-inter text-[16px] font-semibold text-white disabled:opacity-60">
                    <span x-show="! busy">{{ __('Generate and copy report') }}</span>
                    <span x-show="busy" x-cloak>{{ __('Generating…') }}</span>
                </button>

                <p x-show="copied" x-cloak class="flex items-center gap-1.5 font-inter text-[14px] font-semibold text-forest">
                    <flux:icon.check-circle class="size-4" />
                    {{ __('Report copied — paste it into your AI assistant.') }}
                </p>

                @if ($report !== '')
                <div x-show="failed" x-cloak class="space-y-2">
                    <p class="font-inter text-[13.5px] text-muted">{{ __('Could not copy automatically — select and copy the report below.') }}</p>
                    <textarea readonly rows="8" x-on:focus="$el.select()" class="w-full rounded-xl border border-line bg-cream p-3 font-mono text-xs text-ink">{{ $report }}</textarea>
                </div>
                @endif
            </div>

            {{-- 2. Import --}}
            <form wire:submit="import" class="rounded-[22px] border border-line bg-white p-4 space-y-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center size-7 rounded-full bg-sand font-inter text-[14px] font-semibold text-muted">2</span>
                    <h2 class="font-inter text-[17px] font-semibold text-ink">{{ __('Import plan') }}</h2>
                </div>

                <x-ui.textarea wire:model.live.debounce.300ms="importJson" rows="4" placeholder="{{ __('Paste the AI answer or just the JSON') }}" />
                <x-ui.field-error name="importJson" />
                <p class="font-inter text-[13px] text-muted">{{ __('We only fill empty meals — nothing gets overwritten.') }}</p>

                <button
                    type="submit"
                    @disabled(trim($importJson) === '')
                    class="w-full rounded-[16px] border border-line bg-white py-3.5 font-inter text-[15.5px] font-semibold text-ink disabled:text-ink/35">
                    {{ __('Import plan') }}
                </button>

                @if ($importResult)
                <div class="rounded-xl bg-sand p-3 font-inter text-sm text-ink space-y-1">
                    <div>{{ __('Added: :count meals', ['count' => count($importResult['created'])]) }}</div>
                    @if ($importResult['skipped'])
                    <div class="text-muted">{{ __('Skipped (already planned): :list', ['list' => implode(', ', $importResult['skipped'])]) }}</div>
                    @endif
                </div>
                @endif
            </form>

            {{-- API --}}
            <div x-data="{ open: false }" class="px-1">
                <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1.5 font-inter text-[14px] font-semibold text-muted">
                    <flux:icon.code-bracket class="size-4" />
                    {{ __('Import via API') }}
                    <flux:icon.chevron-down class="size-3.5 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-collapse class="pt-2 font-inter text-[13.5px] leading-relaxed text-muted">
                    {{ __('An AI agent can send the same JSON to') }}
                    <code class="text-xs text-ink">POST {{ url('/api/meal-plan') }}</code>
                    {{ __('with the header') }} <code class="text-xs text-ink">Authorization: Bearer &lt;MEAL_PLAN_API_TOKEN&gt;</code>.
                    @if (blank(config('services.meal_plan_api.token')))
                    <span class="block mt-1 text-terracotta-dark">{{ __('The API is disabled — MEAL_PLAN_API_TOKEN is not set.') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
