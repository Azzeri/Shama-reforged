<?php

use App\Actions\MealPlan\BuildWeekReview;
use App\Actions\MealPlan\InsertRecipeIntoPlan;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\RecipeMealAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Week review: the 80/20 health goal, variety by main ingredient and
 * "worth improving" cards for the 7 days the calendar shows. The numbers come
 * from BuildWeekReview; "Insert" puts a suggested recipe into a free place of
 * the plan and stays on its card with "Undo" until the page is left.
 */
new #[Layout('layouts::app')] class extends Component {
    public const CARD_INSERTED = 'inserted';

    /** Start of the reviewed 7 days (Y-m-d); today when empty — same as the calendar. */
    #[Url(as: 'week')]
    public ?string $week = null;

    /**
     * Recipes inserted from this page, newest last.
     *
     * @var list<array{id: int, name: string, assignmentId: int, slot: string, card: string}>
     */
    #[Locked]
    public array $inserted = [];

    public function mount(): void
    {
        try {
            $this->week = $this->week ? Carbon::parse($this->week)->toDateString() : null;
        } catch (\Throwable) {
            $this->week = null;
        }
    }

    public function render()
    {
        return $this->view()->title(__('Week review'));
    }

    #[Computed]
    public function start(): CarbonInterface
    {
        return ($this->week ? Carbon::parse($this->week) : now())->copy()->startOfDay();
    }

    #[Computed]
    public function review(): array
    {
        return app(BuildWeekReview::class)($this->start);
    }

    /**
     * The review's cards with this visit's insertions pinned on top of the
     * card they came from (fresh suggestions fill the rest of its limit).
     * Insertions whose card is gone — e.g. the goal got reached — end up in
     * an "Added to the plan" card, so "Undo" stays available.
     */
    #[Computed]
    public function cards(): array
    {
        $inserter = app(InsertRecipeIntoPlan::class);
        $freeSlots = $inserter->freeSlots($this->start);
        $recipes = Recipe::query()->with('tags')
            ->findMany(collect($this->review['cards'])->flatMap(fn (array $card) => array_column($card['suggestions'], 'id')))
            ->keyBy('id');

        $pinnedOf = fn (string $key) => array_values(array_map(
            fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'sub' => __('In the plan: :slot', ['slot' => $row['slot']]), 'inserted' => true, 'canInsert' => false],
            array_filter($this->inserted, fn (array $row) => $row['card'] === $key),
        ));

        $cards = array_map(function (array $card) use ($pinnedOf, $inserter, $freeSlots, $recipes) {
            $pinned = $pinnedOf($card['key']);
            $fresh = array_slice($card['suggestions'], 0, max(0, $card['limit'] - count($pinned)));

            $card['suggestions'] = [...$pinned, ...array_map(fn (array $suggestion) => [
                'id' => $suggestion['id'],
                'name' => $suggestion['name'],
                'sub' => $suggestion['last'],
                'inserted' => false,
                'canInsert' => $recipes->has($suggestion['id']) && $inserter->slotFor($recipes[$suggestion['id']], $freeSlots) !== null,
            ], $fresh)];

            return $card;
        }, $this->review['cards']);

        $keys = array_column($cards, 'key');
        $orphans = array_values(array_filter($this->inserted, fn (array $row) => ! in_array($row['card'], $keys, true)));

        if ($orphans !== []) {
            $cards[] = [
                'key' => self::CARD_INSERTED,
                'title' => __('Added to the plan'),
                'body' => '',
                'suggestions' => array_merge(...array_map($pinnedOf, array_unique(array_column($orphans, 'card')))),
                'limit' => 0,
                'day' => null,
            ];
        }

        return $cards;
    }

    public function insert(int $recipeId, string $card): void
    {
        $recipe = Recipe::query()->plannable()->with('tags')->find($recipeId);

        if (! $recipe || in_array($recipeId, array_column($this->inserted, 'id'), true)) {
            return;
        }

        $result = app(InsertRecipeIntoPlan::class)($recipe, $this->start);

        if ($result === null) {
            return;
        }

        $day = Carbon::parse($result['date'])->locale(app()->getLocale())->isoFormat('dddd');

        $this->inserted[] = [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'assignmentId' => $result['assignment']->id,
            'slot' => $day.', '.mb_strtolower(Meal::typeLabel($result['type'])),
            'card' => $card,
        ];

        unset($this->review, $this->cards);
    }

    /** Takes back exactly the entry "Insert" created. */
    public function undo(int $recipeId): void
    {
        $row = collect($this->inserted)->firstWhere('id', $recipeId);

        if (! $row) {
            return;
        }

        $assignment = RecipeMealAssignment::query()->whereKey($row['assignmentId'])->where('recipe_id', $recipeId)->first();

        if ($assignment) {
            $mealId = $assignment->meal_id;
            $assignment->delete();
            Meal::query()->whereKey($mealId)->whereNull(Meal::NOTE_COLUMN)->whereDoesntHave('recipes')->delete();
        }

        $this->inserted = array_values(array_filter($this->inserted, fn (array $row) => $row['id'] !== $recipeId));

        unset($this->review, $this->cards);
    }

    #[Computed]
    public function incompleteCount(): int
    {
        return Recipe::query()->incomplete()->count();
    }

    /** "18 – 24 listopada" */
    public function rangeLabel(): string
    {
        $start = $this->review['start']->copy()->locale(app()->getLocale());
        $end = $this->review['end']->copy()->locale(app()->getLocale());

        return $start->isSameMonth($end)
            ? $start->format('j').' – '.$end->isoFormat('D MMMM')
            : $start->isoFormat('D MMM').' – '.$end->isoFormat('D MMM');
    }
};
?>

@php
    $review = $this->review;
    $health = $review['health'];
    $variety = $review['variety'];
    $cards = $this->cards;
    $weekParam = $week ? ['week' => $week] : [];
    $segments = [
        ['label' => Recipe::ratingLabel(Recipe::RATING_HEALTHY), 'count' => $health['healthy'], 'class' => Recipe::ratingDotClass(Recipe::RATING_HEALTHY)],
        ['label' => Recipe::ratingLabel(Recipe::RATING_MEDIUM), 'count' => $health['medium'], 'class' => Recipe::ratingDotClass(Recipe::RATING_MEDIUM)],
        ['label' => Recipe::ratingLabel(Recipe::RATING_UNHEALTHY), 'count' => $health['unhealthy'], 'class' => Recipe::ratingDotClass(Recipe::RATING_UNHEALTHY)],
    ];
    $badgeOk = 'bg-sage-light text-forest';
    $badgeOff = 'bg-today text-today-ink';
@endphp

<div class="space-y-3 lg:space-y-4">
    <x-ui.sub-header :title="__('Week review')" :back-href="route('meals.index', $weekParam)" :back-label="__('Plan')">
        <p class="text-[13px] lg:text-[14px] text-ink-soft">
            {{ $this->rangeLabel() }}
            @if ($review['meals'] > 0)
            · {{ trans_choice(':count meal|:count meals', $review['meals']) }} {{ trans_choice('in :count day|in :count days', $review['days']) }}
            @endif
        </p>
    </x-ui.sub-header>

    <div class="space-y-3 lg:flex lg:items-start lg:gap-6 lg:space-y-0">
        <div class="space-y-3 lg:w-[400px] lg:shrink-0">
            {{-- Health: the 80/20 goal. --}}
            <section class="rounded-[20px] border border-line bg-surface px-4 pt-3.5 pb-4 space-y-3">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="text-[11px] font-semibold uppercase tracking-[0.09em] text-muted">{{ __('Health') }}</h2>
                    <span class="text-[12px] font-semibold text-ink-soft">{{ __('goal: :min% healthy, :max% unhealthy', ['min' => $health['minPercent'], 'max' => $health['maxPercent']]) }}</span>
                </div>

                @if ($health['rated'] === 0)
                <p class="text-[13px] leading-normal text-ink-soft">{{ __('No rated recipes in the plan for these days yet.') }}</p>
                @else
                <div class="relative pt-5">
                    <div class="flex h-3.5 gap-0.5 overflow-hidden rounded-full">
                        @foreach ($segments as $segment)
                        @if ($segment['count'] > 0)
                        <span class="{{ $segment['class'] }}" style="flex: {{ $segment['count'] }} 1 0"></span>
                        @endif
                        @endforeach
                    </div>
                    <span class="absolute top-0 -translate-x-1/2 whitespace-nowrap text-[11px] font-semibold text-ink" style="left: {{ $health['minPercent'] }}%">{{ __('goal :percent%', ['percent' => $health['minPercent']]) }}</span>
                    <span class="absolute top-4 -ml-px h-[22px] w-0.5 rounded-sm bg-ink" style="left: {{ $health['minPercent'] }}%"></span>
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    @foreach ($segments as $segment)
                    <span class="flex items-center gap-1.5 text-[13px] text-ink-soft">
                        <span class="size-2 rounded-full {{ $segment['class'] }}"></span>
                        {{ $segment['label'] }}
                        <span class="font-semibold text-ink tabular-nums">{{ $segment['count'] }}</span>
                    </span>
                    @endforeach
                </div>

                <div>
                    <div class="flex items-center gap-2.5 border-t border-divider py-2.5">
                        <span class="w-[58px] font-fraunces text-[24px] font-semibold leading-none text-ink tabular-nums">{{ $health['healthyPercent'] }}%</span>
                        <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                            <span class="text-[14px] font-semibold text-ink">{{ __('Healthy meals') }}</span>
                            <span class="text-[12px] text-muted">{{ __('goal: at least :percent%', ['percent' => $health['minPercent']]) }}</span>
                        </span>
                        @if ($health['enough'])
                        <span class="shrink-0 rounded-full px-2 py-[3px] text-[11px] font-semibold whitespace-nowrap {{ $health['healthyOk'] ? $badgeOk : $badgeOff }}">{{ $health['healthyOk'] ? __('within goal') : __('below goal') }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2.5 border-t border-divider pt-2.5">
                        <span class="w-[58px] font-fraunces text-[24px] font-semibold leading-none text-ink tabular-nums">{{ $health['unhealthyPercent'] }}%</span>
                        <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                            <span class="text-[14px] font-semibold text-ink">{{ __('Unhealthy meals') }}</span>
                            <span class="text-[12px] text-muted">{{ __('goal: at most :percent%', ['percent' => $health['maxPercent']]) }}</span>
                        </span>
                        @if ($health['enough'])
                        <span class="shrink-0 rounded-full px-2 py-[3px] text-[11px] font-semibold whitespace-nowrap {{ $health['unhealthyOk'] ? $badgeOk : $badgeOff }}">{{ $health['unhealthyOk'] ? __('within goal') : __('over goal') }}</span>
                        @endif
                    </div>
                </div>

                <p class="text-[13px] leading-normal text-ink-soft">
                    {{ trans_choice('Healthy: :healthy of :count rated meal.|Healthy: :healthy of :count rated meals.', $health['rated'], ['healthy' => $health['healthy']]) }}
                    @if (! $health['enough'])
                    {{ trans_choice('The goal is judged from :count rated meal up.|The goal is judged from :count rated meals up.', (int) config('meals.review.min_meals', 5)) }}
                    @elseif ($health['missingHealthy'] > 0)
                    {{ trans_choice(':count more healthy one is needed to reach :percent%.|:count more healthy ones are needed to reach :percent%.', $health['missingHealthy'], ['percent' => $health['minPercent']]) }}
                    @elseif ($health['unhealthyOk'])
                    {{ __('Goal reached.') }}
                    @endif
                    {{ __('Medium meals count for neither side.') }}
                </p>
                @endif

                @if ($health['unrated'] > 0)
                <p class="text-[12px] leading-normal text-muted">{{ trans_choice('Skipped: :count meal whose recipe has no rating.|Skipped: :count meals whose recipes have no rating.', $health['unrated']) }}</p>
                @endif
            </section>

            {{-- Variety: meals per main ingredient. --}}
            <section class="rounded-[20px] border border-line bg-surface px-4 pt-3.5 pb-4 space-y-2.5">
                <h2 class="text-[11px] font-semibold uppercase tracking-[0.09em] text-muted">{{ __('Variety') }}</h2>

                @if ($variety['tagged'] === 0)
                <p class="text-[13px] leading-normal text-ink-soft">{{ __('No recipes with a main ingredient in the plan for these days yet.') }}</p>
                @else
                <div class="space-y-2">
                    @foreach ($variety['groups'] as $group)
                    <div wire:key="group-{{ $group['id'] }}" class="flex items-center gap-2.5">
                        <span @class(['w-28 shrink-0 text-[13px]', 'text-ink' => $group['count'] > 0, 'text-muted' => $group['count'] === 0])>{{ $group['label'] }}</span>
                        <span class="flex h-2 flex-1 rounded-full bg-divider"><span class="rounded-full bg-sage" style="width: {{ min(100, $group['percent']) }}%"></span></span>
                        <span @class(['w-5 text-right text-[13px] font-semibold tabular-nums', 'text-ink' => $group['count'] > 0, 'text-muted' => $group['count'] === 0])>{{ $group['count'] }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                @if ($review['meals'] > 0)
                <p class="text-[13px] leading-normal text-ink-soft">
                    {{ trans_choice(':count different recipe|:count different recipes', $variety['distinct']) }} {{ trans_choice('for :count meal|for :count meals', $review['meals']) }}.
                    @if ($variety['mostRepeated'])
                    {{ __('“:name” is in the plan :count times.', $variety['mostRepeated']) }}
                    @endif
                </p>
                @endif

                @if ($variety['untagged'] > 0)
                <p class="text-[12px] leading-normal text-muted">{{ trans_choice('Skipped: :count meal whose recipe has no main ingredient.|Skipped: :count meals whose recipes have no main ingredient.', $variety['untagged']) }}</p>
                @endif
            </section>

            <div class="hidden lg:block">
                @include('pages.meal.partials.review-footnote')
            </div>
        </div>

        <div class="space-y-3 lg:flex-1 lg:min-w-0 lg:max-w-[640px]">
            @if ($cards !== [])
            <h2 class="px-1 pt-2 lg:pt-0 font-fraunces text-[20px] lg:text-[22px] font-semibold text-ink">{{ __('Worth improving') }}</h2>
            @endif

            @foreach ($cards as $card)
            <section wire:key="card-{{ $card['key'] }}" class="rounded-[20px] border border-line bg-surface px-4 py-3.5 space-y-2">
                <h3 class="text-[15px] font-semibold leading-snug text-ink">{{ $card['title'] }}</h3>
                @if ($card['body'] !== '')
                <p class="text-[13px] leading-normal text-ink-soft">{{ $card['body'] }}</p>
                @endif

                @if ($card['suggestions'] !== [])
                <div>
                    @foreach ($card['suggestions'] as $suggestion)
                    <div wire:key="suggestion-{{ $card['key'] }}-{{ $suggestion['id'] }}" class="flex min-h-[52px] items-center gap-2.5 border-t border-divider py-1">
                        <span class="flex-1 min-w-0 flex flex-col gap-0.5">
                            <span class="text-[14px] font-medium leading-tight text-ink">{{ $suggestion['name'] }}</span>
                            <span @class(['text-[12px]', 'text-forest' => $suggestion['inserted'], 'text-muted' => ! $suggestion['inserted']])>{{ $suggestion['sub'] }}</span>
                        </span>
                        @if ($suggestion['inserted'])
                        <button type="button" wire:click="undo({{ $suggestion['id'] }})" class="h-10 shrink-0 px-2.5 text-[13px] font-semibold text-terracotta">{{ __('Undo') }}</button>
                        @else
                        <button type="button" wire:click="insert({{ $suggestion['id'] }}, '{{ $card['key'] }}')" wire:loading.attr="disabled"
                            @disabled(! $suggestion['canInsert'])
                            aria-label="{{ __('Insert into the plan: :name', ['name' => $suggestion['name']]) }}"
                            @if (! $suggestion['canInsert']) title="{{ __('No free place for this recipe in these days.') }}" @endif
                            class="h-10 shrink-0 rounded-full border border-field bg-surface px-3.5 text-[13px] font-semibold text-ink disabled:opacity-40">{{ __('Insert') }}</button>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if ($card['day'])
                @php $dayName = \Illuminate\Support\Carbon::parse($card['day'])->locale(app()->getLocale())->isoFormat('dddd'); @endphp
                <a href="{{ route('meals.day.edit', $card['day']) }}" wire:navigate class="lg:hidden inline-flex min-h-10 items-center gap-1.5 text-[14px] font-semibold text-terracotta">
                    {{ __('Edit day') }}: {{ $dayName }}
                    <x-icon.chevron-right class="size-3.5" />
                </a>
                <a href="{{ route('meals.index', [...$weekParam, 'day' => $card['day']]) }}" wire:navigate class="hidden lg:inline-flex min-h-10 items-center gap-1.5 text-[14px] font-semibold text-terracotta">
                    {{ __('Edit day') }}: {{ $dayName }}
                    <x-icon.chevron-right class="size-3.5" />
                </a>
                @endif
            </section>
            @endforeach

            @if ($cards === [] && $review['meals'] > 0)
            <p class="rounded-[20px] border-[1.5px] border-dashed border-field px-3.5 py-5 text-center text-[14px] leading-normal text-ink-soft">{{ __('Nothing to improve in this plan.') }}</p>
            @endif

            <div class="lg:hidden">
                @include('pages.meal.partials.review-footnote')
            </div>
        </div>
    </div>
</div>
