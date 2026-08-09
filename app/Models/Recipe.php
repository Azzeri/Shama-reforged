<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'content', 'link',
    'calories_me', 'protein_me', 'fat_me', 'carbs_me',
    'calories_wife', 'protein_wife', 'fat_wife', 'carbs_wife',
])]
class Recipe extends Model
{
    use HasFactory;

    public const NAME_COLUMN = 'name';

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
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class, 'recipe_meal_assignments')
            ->using(RecipeMealAssignment::class)
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag_assignments')
            ->withTimestamps();
    }
}