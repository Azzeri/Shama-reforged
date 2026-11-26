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

    /** The user's prompt template (the default one until they change it). */
    public string $promptTemplate = '';

    public function render()
    {
        return $this->view()->title(__('AI meal plan'));
    }

    public function mount(): void
    {
        $this->historyFrom = now()->subDays(28)->toDateString();
        $this->historyTo = now()->toDateString();
        $this->planFrom = now()->addDay()->toDateString();
        $this->promptTemplate = auth()->user()->ai_prompt_template ?? BuildMealPlanReport::DEFAULT_TEMPLATE;
    }

    public function isPromptChanged(): bool
    {
        return $this->normalized($this->promptTemplate) !== $this->normalized(BuildMealPlanReport::DEFAULT_TEMPLATE);
    }

    private function normalized(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    /** Saved on every edit; the default template is stored as null (follows future code changes). */
    public function updatedPromptTemplate(): void
    {
        $this->validate(['promptTemplate' => ['nullable', 'string', 'max:20000']]);

        auth()->user()->update([
            'ai_prompt_template' => $this->isPromptChanged() && trim($this->promptTemplate) !== '' ? $this->promptTemplate : null,
        ]);
    }

    public function resetPrompt(): void
    {
        $this->promptTemplate = BuildMealPlanReport::DEFAULT_TEMPLATE;
        auth()->user()->update(['ai_prompt_template' => null]);
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
            auth()->user()->ai_prompt_template,
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
        <div class="space-y-3 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6 lg:space-y-0">
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
                class="rounded-[20px] border border-line bg-white p-4 space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center size-7 rounded-full bg-ink text-[14px] font-semibold text-white">1</span>
                    <h2 class="text-[17px] font-semibold text-ink">{{ __('Report for the AI') }}</h2>
                </div>

                <div class="rounded-2xl border border-line bg-field-bg divide-y divide-line" x-data="{ editHistory: false, editPlan: false }">
                    <div class="px-3.5 py-2.5">
                        <button type="button" x-on:click="editHistory = ! editHistory" x-bind:aria-expanded="editHistory" class="w-full flex items-center gap-3 min-h-11 text-left">
                            <span class="flex-1">
                                <span class="block text-[13px] text-muted">{{ __('Based on the history') }}</span>
                                <span class="block text-[16px] font-medium text-ink">{{ $this->historyLabel }}</span>
                            </span>
                            <x-icon.calendar class="size-5 text-ink-soft" />
                        </button>
                        <div x-show="editHistory" x-collapse class="grid grid-cols-2 gap-2 pt-2">
                            <input type="date" wire:model.live="historyFrom" aria-label="{{ __('History from') }}" class="w-full h-11 rounded-xl border border-field bg-white px-2.5 text-base text-ink" />
                            <input type="date" wire:model.live="historyTo" aria-label="{{ __('History to') }}" class="w-full h-11 rounded-xl border border-field bg-white px-2.5 text-base text-ink" />
                        </div>
                        <x-ui.field-error name="historyTo" />
                    </div>

                    <div class="px-3.5 py-2.5">
                        <div class="flex items-center gap-3">
                            <button type="button" x-on:click="editPlan = ! editPlan" x-bind:aria-expanded="editPlan" class="flex-1 min-h-11 text-left">
                                <span class="block text-[13px] text-muted">{{ __('Plan for') }}</span>
                                <span class="block text-[16px] font-medium text-ink">{{ $this->planLabel }}</span>
                            </button>
                            <x-ui.stepper :value="$planDays" :min="1" :max="31" :label="trans_choice(':count day|:count days', $planDays)" decrement="changePlanDays(-1)" increment="changePlanDays(1)" />
                        </div>
                        <div x-show="editPlan" x-collapse class="pt-2">
                            <input type="date" wire:model.live="planFrom" aria-label="{{ __('Plan from') }}" class="w-full h-11 rounded-xl border border-field bg-white px-2.5 text-base text-ink" />
                        </div>
                    </div>
                </div>

                <div>
                    <label for="ai-ingredients" class="block mb-1.5 text-[14px] font-semibold text-ink">{{ __('Ingredients to use') }} <span class="font-normal text-muted">· {{ __('optionally') }}</span></label>
                    <x-ui.textarea id="ai-ingredients" wire:model="ingredientsToUse" rows="2" placeholder="{{ __('e.g. zucchini, feta, half a chicken breast') }}" />
                </div>

                <div>
                    <label for="ai-wishes" class="block mb-1.5 text-[14px] font-semibold text-ink">{{ __('Other wishes') }} <span class="font-normal text-muted">· {{ __('optionally') }}</span></label>
                    <x-ui.textarea id="ai-wishes" wire:model="extraWishes" rows="2" placeholder="{{ __('e.g. fish twice, quick dinners on weekdays') }}" />
                </div>

                <div x-data="{ open: window.isDesktop() }" class="border-t border-divider pt-3 space-y-2">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="flex w-full items-center gap-2 min-h-11 text-left">
                        <span class="flex-1 text-[15px] font-semibold text-ink">
                            {{ __('Prompt for the AI') }}
                            @if ($this->isPromptChanged())
                            <span class="ml-1 rounded-full bg-gold-light px-2 py-0.5 text-[11px] font-semibold text-gold-dark">{{ __('changed') }}</span>
                            @endif
                        </span>
                        <span class="text-[14px] text-muted" x-text="open ? @js(__('Collapse')) : @js(__('Preview and edit'))"></span>
                        <x-icon.chevron-down class="size-4 text-muted transition-transform" x-bind:class="open && 'rotate-180'" />
                    </button>
                    <div x-show="open" x-collapse x-cloak class="space-y-2">
                        <label for="ai-prompt" class="sr-only">{{ __('Prompt for the AI') }}</label>
                        <textarea id="ai-prompt" wire:model.live.debounce.800ms="promptTemplate" rows="12"
                            class="w-full rounded-xl border border-field bg-field-bg p-3 font-mono text-[13px] leading-relaxed text-ink focus:outline-none focus:border-ink focus:ring-1 focus:ring-ink"></textarea>
                        <x-ui.field-error name="promptTemplate" />
                        <p class="text-[13px] leading-normal text-muted">{{ __('Fields in {braces} are filled in automatically from the form and the history:') }} <span class="font-mono text-[12px]">{{ collect(\App\Actions\MealPlan\BuildMealPlanReport::FIELDS)->map(fn ($field) => '{' . $field . '}')->implode(' ') }}</span></p>
                        @if ($this->isPromptChanged())
                        <button type="button" wire:click="resetPrompt" class="inline-flex h-10 items-center gap-1.5 text-[14px] font-semibold text-terracotta">
                            <x-icon.undo class="size-4" />
                            {{ __('Restore the default') }}
                        </button>
                        @endif
                    </div>
                </div>

                <button
                    type="button"
                    x-on:click="generate()"
                    x-bind:disabled="busy"
                    x-bind:class="copied ? 'bg-sage' : 'bg-terracotta'"
                    class="flex w-full h-12 items-center justify-center gap-2 rounded-[14px] text-[15px] font-semibold text-white disabled:opacity-60">
                    <span x-show="! busy && ! copied">{{ __('Generate and copy report') }}</span>
                    <span x-show="busy" x-cloak>{{ __('Generating…') }}</span>
                    <span x-show="! busy && copied" x-cloak class="inline-flex items-center gap-1.5"><x-icon.check class="size-4" /> {{ __('Report copied') }}</span>
                </button>

                @if ($report !== '')
                <div x-show="failed" x-cloak class="space-y-2">
                    <p class="text-[13.5px] text-muted">{{ __('Could not copy automatically — select and copy the report below.') }}</p>
                    <textarea readonly rows="8" x-on:focus="$el.select()" aria-label="{{ __('Report for the AI') }}" class="w-full rounded-xl border border-field bg-field-bg p-3 font-mono text-xs text-ink">{{ $report }}</textarea>
                </div>
                @endif
            </div>

            <div class="space-y-3">
            {{-- 2. Import --}}
            <form wire:submit="import" class="rounded-[20px] border border-line bg-white p-4 space-y-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center size-7 rounded-full bg-sand text-[14px] font-semibold text-ink-soft">2</span>
                    <h2 class="text-[17px] font-semibold text-ink">{{ __('Import plan') }}</h2>
                </div>

                <label for="ai-import" class="sr-only">{{ __('Paste the AI answer or just the JSON') }}</label>
                <x-ui.textarea id="ai-import" wire:model.live.debounce.300ms="importJson" rows="4" placeholder="{{ __('Paste the AI answer or just the JSON') }}" />
                <x-ui.field-error name="importJson" />
                <p class="text-[13px] text-muted">{{ __('We only fill empty meals — nothing gets overwritten.') }}</p>

                <button type="submit" @disabled(trim($importJson) === '')
                    class="w-full h-12 rounded-[14px] bg-terracotta text-[15px] font-semibold text-white disabled:bg-white disabled:border disabled:border-field disabled:text-muted">
                    {{ __('Import plan') }}
                </button>

                @if ($importResult)
                <div role="status" class="rounded-xl bg-sage-light p-3 text-sm text-forest space-y-1">
                    <div>{{ __('Added: :count meals', ['count' => count($importResult['created'])]) }}</div>
                    @if ($importResult['skipped'])
                    <div class="text-muted">{{ __('Skipped (already planned): :list', ['list' => implode(', ', $importResult['skipped'])]) }}</div>
                    @endif
                </div>
                @endif
            </form>

            {{-- API --}}
            <div x-data="{ open: false }" class="px-1">
                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" class="inline-flex h-11 items-center gap-1.5 text-[14px] font-semibold text-ink-soft">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 6-6 6 6 6"/><path d="m16 6 6 6-6 6"/></svg>
                    {{ __('Import via API') }}
                    <x-icon.chevron-down class="size-3.5 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-collapse class="pt-1 text-[13.5px] leading-relaxed text-muted">
                    {{ __('An AI agent can send the same JSON to') }}
                    <code class="text-xs text-ink">POST {{ url('/api/meal-plan') }}</code>
                    {{ __('with the header') }} <code class="text-xs text-ink">Authorization: Bearer &lt;MEAL_PLAN_API_TOKEN&gt;</code>.
                    @if (blank(config('services.meal_plan_api.token')))
                    <span class="block mt-1 text-terracotta">{{ __('The API is disabled — MEAL_PLAN_API_TOKEN is not set.') }}</span>
                    @endif
                </div>
            </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
