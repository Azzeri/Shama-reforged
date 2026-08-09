<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL's ALTER TABLE (used below to reset AUTO_INCREMENT) implicitly
     * commits any open transaction, so wrapping this migration in one would
     * only give a false sense of atomicity.
     */
    public $withinTransaction = false;

    /**
     * Wipes all meal-planning content (recipes, ingredients, meals, shopping
     * lists and their pivot assignments) for a clean seed. Tags and users
     * are left untouched — tags are reference data the recipe form depends
     * on, not "content".
     */
    public function up(): void
    {
        $tables = [
            'shopping_list_items',
            'recipe_ingredient_assignments',
            'recipe_meal_assignments',
            'recipe_tag_assignments',
            'shopping_lists',
            'meals',
            'recipes',
            'ingredients',
        ];

        foreach ($tables as $table) {
            DB::table($table)->delete();
        }

        // Reset auto-increment IDs for a clean seed. The mechanism is
        // driver-specific (sqlite_sequence is a real, queryable table only
        // on SQLite); unrecognized drivers just skip this cosmetic step
        // rather than failing the deploy.
        match (DB::getDriverName()) {
            'sqlite' => DB::table('sqlite_sequence')->whereIn('name', $tables)->delete(),
            'mysql' => collect($tables)->each(fn (string $table) => DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1")),
            default => null,
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
