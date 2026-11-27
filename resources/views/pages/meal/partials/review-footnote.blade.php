{{-- Week review: what the numbers are based on + the way to the unlabelled recipes. --}}
<p class="px-1 pt-1 text-[12px] leading-normal text-muted">{{ __('The review uses recipe labels and the plan history. Notes and recipes without labels are skipped.') }}</p>
@if ($this->incompleteCount > 0)
<a href="{{ route('recipes.incomplete') }}" wire:navigate class="inline-flex min-h-11 items-center px-1 text-[14px] font-semibold text-ink-soft">{{ __('Recipes to fill in') }} ({{ $this->incompleteCount }})</a>
@endif
