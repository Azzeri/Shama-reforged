<?php

use App\Actions\MealPlan\BuildMealPlanReport;
use App\Actions\MealPlan\ImportMealPlan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('AI meal plan')] class extends Component {
    public string $historyFrom = '';

    public string $historyTo = '';

    public string $planFrom = '';

    public int $planDays = 7;

    public string $ingredientsToUse = '';

    public string $extraWishes = '';

    public string $report = '';

    public string $importJson = '';

    public ?array $importResult = null;

    public function mount(): void
    {
        $this->historyFrom = now()->subDays(28)->toDateString();
        $this->historyTo = now()->toDateString();
        $this->planFrom = now()->addDay()->toDateString();
    }

    public function generate(BuildMealPlanReport $build): void
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
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('AI meal plan')" :subheading="__('Generate a report for an AI assistant, then import the plan it suggests.')">
        <form wire:submit="generate" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <flux:input type="date" wire:model="historyFrom" :label="__('History from')" />
                <flux:input type="date" wire:model="historyTo" :label="__('History to')" />
                <flux:input type="date" wire:model="planFrom" :label="__('Plan from')" />
                <flux:input type="number" min="1" max="31" wire:model="planDays" :label="__('Days to plan')" />
            </div>

            <flux:textarea wire:model="ingredientsToUse" rows="2" :label="__('Ingredients to use (optional)')" :placeholder="__('e.g. zucchini, feta, half a chicken breast')" />
            <flux:textarea wire:model="extraWishes" rows="2" :label="__('Other wishes (optional)')" :placeholder="__('e.g. fish twice, quick dinners on weekdays')" />

            <flux:button type="submit" variant="primary" class="w-full">{{ __('Generate report') }}</flux:button>
        </form>

        @if ($report !== '')
        <div class="mt-6 space-y-2" x-data="{ copied: false }">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">{{ __('Report') }}</flux:heading>
                <flux:button
                    size="sm"
                    icon="clipboard"
                    x-on:click="navigator.clipboard.writeText($refs.report.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                    <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                </flux:button>
            </div>
            <textarea x-ref="report" readonly rows="12" class="w-full rounded-xl border border-ink/15 bg-white p-3 font-mono text-xs text-ink">{{ $report }}</textarea>
        </div>
        @endif

        <form wire:submit="import" class="mt-8 space-y-3">
            <flux:heading size="sm">{{ __('Import plan') }}</flux:heading>
            <flux:text>{{ __('Paste the AI answer (or just its JSON). Only empty meals are filled, nothing is overwritten.') }}</flux:text>
            <flux:textarea wire:model="importJson" rows="5" class="font-mono" />
            <flux:error name="importJson" />
            <flux:button type="submit" class="w-full">{{ __('Import') }}</flux:button>

            @if ($importResult)
            <div class="rounded-xl bg-sand p-3 font-manrope text-sm text-ink space-y-1">
                <div>{{ __('Added: :count meals', ['count' => count($importResult['created'])]) }}</div>
                @if ($importResult['skipped'])
                <div class="text-ink/60">{{ __('Skipped (already planned): :list', ['list' => implode(', ', $importResult['skipped'])]) }}</div>
                @endif
            </div>
            @endif
        </form>

        <div class="mt-8 space-y-1">
            <flux:heading size="sm">{{ __('API') }}</flux:heading>
            <flux:text>
                {{ __('An AI agent can send the same JSON to') }}
                <code class="text-xs">POST {{ url('/api/meal-plan') }}</code>
                {{ __('with the header') }} <code class="text-xs">Authorization: Bearer &lt;MEAL_PLAN_API_TOKEN&gt;</code>.
                @if (blank(config('services.meal_plan_api.token')))
                <span class="text-terracotta-dark">{{ __('The API is disabled — MEAL_PLAN_API_TOKEN is not set.') }}</span>
                @endif
            </flux:text>
        </div>
    </x-pages::settings.layout>
</section>
