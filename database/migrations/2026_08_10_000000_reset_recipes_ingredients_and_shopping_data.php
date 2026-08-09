<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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

        DB::table('sqlite_sequence')->whereIn('name', $tables)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
