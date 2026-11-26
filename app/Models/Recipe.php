<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'content', 'link', 'servings', 'rating', 'packable', 'archived_at',
    'calories_me', 'protein_me', 'fat_me', 'carbs_me',
    'calories_wife', 'protein_wife', 'fat_wife', 'carbs_wife',
])]
class Recipe extends Model
{
    use HasFactory;

    /** Same defaults as the DB columns, so a freshly created model knows them. */
    protected $attributes = [
        'servings' => 2,
        'packable' => false,
    ];

    public const NAME_COLUMN = 'name';

    public const RATING_HEALTHY = 'healthy';

    public const RATING_MEDIUM = 'medium';

    public const RATING_UNHEALTHY = 'unhealthy';

    public const RATINGS = [self::RATING_HEALTHY, self::RATING_MEDIUM, self::RATING_UNHEALTHY];

    public static function ratingLabel(string $rating): string
    {
        return match ($rating) {
            self::RATING_HEALTHY => __('healthy'),
            self::RATING_MEDIUM => __('medium'),
            self::RATING_UNHEALTHY => __('unhealthy'),
            default => $rating,
        };
    }

    /** Tailwind background class of the rating dot. */
    public static function ratingDotClass(string $rating): string
    {
        return match ($rating) {
            self::RATING_HEALTHY => 'bg-sage',
            self::RATING_MEDIUM => 'bg-gold',
            default => 'bg-terracotta',
        };
    }

    /** Servings one planned meal uses up (see config/meals.php). */
    public static function servingsPerMeal(): int
    {
        return max(1, (int) config('meals.servings_per_meal', 2));
    }

    /** "2 porcje" */
    public static function servingsLabel(int $servings): string
    {
        return trans_choice(':count serving|:count servings', $servings);
    }

    /**
     * How many days after the cooking day a recipe cooked whole still feeds
     * the household (servings left after the cooking-day meal).
     */
    public function maxLeftoverDays(): int
    {
        return max(0, intdiv((int) $this->servings, self::servingsPerMeal()) - 1);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public const NUTRITION_PROFILES = ['me', 'wife'];

    public static function nutritionProfileLabel(string $profile): string
    {
        return match ($profile) {
            'me' => 'Mariusz',
            'wife' => 'Natalia',
            default => ucfirst($profile),
        };
    }

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'packable' => 'boolean',
            'archived_at' => 'datetime',
            'calories_me' => 'integer',
            'protein_me' => 'float',
            'fat_me' => 'float',
            'carbs_me' => 'float',
            'calories_wife' => 'integer',
            'protein_wife' => 'float',
            'fat_wife' => 'float',
            'carbs_wife' => 'float',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::ucfirst(trim($value)),
        );
    }

    public function nutritionFor(string $profile): array
    {
        return [
            'calories' => $this->{"calories_{$profile}"},
            'protein' => $this->{"protein_{$profile}"},
            'fat' => $this->{"fat_{$profile}"},
            'carbs' => $this->{"carbs_{$profile}"},
        ];
    }

    public function hasNutritionFor(string $profile): bool
    {
        return collect($this->nutritionFor($profile))->contains(fn ($value) => filled($value));
    }

    public function hasAnyNutrition(): bool
    {
        return collect(self::NUTRITION_PROFILES)->contains(fn (string $profile) => $this->hasNutritionFor($profile));
    }

    public function caloriesFor(string $profile): ?int
    {
        return $this->{"calories_{$profile}"};
    }

    /**
     * Recipe show URL carrying a "back" param pointing to wherever the user
     * is currently looking at. On a normal page render the current request
     * IS that page, but content rendered/re-rendered during a Livewire AJAX
     * call (an action method, or markup that only appears after one, like a
     * modal) sees Livewire's update endpoint as the request instead — there
     * the actual page is the Referer.
     */
    public function showUrlWithBack(): string
    {
        if (\Livewire\Livewire::isLivewireRequest()) {
            $referer = request()->headers->get('referer');
            $back = null;

            if ($referer) {
                $path = parse_url($referer, PHP_URL_PATH) ?: '/';
                $query = parse_url($referer, PHP_URL_QUERY);
                $back = $query ? "{$path}?{$query}" : $path;
            }
        } else {
            $back = request()->getRequestUri();
        }

        return route('recipes.show', $this) . ($back ? '?back=' . urlencode($back) : '');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>  $recipes
     * @return array<string, int> profile => total calories, only profiles with a positive total
     */
    /** "M 601 · N 414 kcal" — per-profile calories, profile shortened to its initial. */
    public static function shortCalorieLine(array $totals): string
    {
        if (empty($totals)) {
            return '';
        }

        return collect($totals)
            ->map(fn (int $kcal, string $profile) => mb_substr(self::nutritionProfileLabel($profile), 0, 1) . ' ' . $kcal)
            ->implode(' · ') . ' kcal';
    }

    public static function calorieTotals(\Illuminate\Support\Collection $recipes): array
    {
        return collect(self::NUTRITION_PROFILES)
            ->mapWithKeys(fn (string $profile) => [$profile => (int) $recipes->sum->{"calories_{$profile}"}])
            ->filter(fn (int $total) => $total > 0)
            ->all();
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'recipe_ingredient_assignments')
            ->using(RecipeIngredientAssignment::class)
            ->withPivot('id', 'quantity', 'amount', 'amount_me', 'amount_wife', 'unit', 'is_optional', 'substitute')
            ->withTimestamps();
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class, 'recipe_meal_assignments')
            ->using(RecipeMealAssignment::class)
            ->withPivot('id', 'mode', 'cooked_from_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag_assignments')
            ->withTimestamps();
    }
}