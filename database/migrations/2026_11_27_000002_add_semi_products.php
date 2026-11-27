<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Semi-products: an ingredient linked to a recipe that makes it (e.g.
     * vegetable stock → "Homemade vegetable stock"). Such an ingredient is
     * either bought ready or made at home — then the linked recipe's
     * ingredients go on the shopping list instead.
     *
     * The choice is kept on three levels (the later wins): the ingredient's
     * default, the one remembered for a recipe, the one made when putting the
     * recipe into the plan.
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->boolean('homemade_default')->default(false);
            // How much of the ingredient the linked recipe makes (e.g. 1500 ml),
            // to scale its ingredients to the amount a dish needs.
            $table->decimal('recipe_yield_amount', 10, 2)->nullable();
            $table->string('recipe_yield_unit', 16)->nullable();
        });

        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            // null = follow the ingredient's default, otherwise buy | make.
            $table->string('semi_source', 8)->nullable();
        });

        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            // {ingredient_id: buy | make | have} chosen when the recipe was planned.
            $table->json('semi_choices')->nullable();
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            // The semi-product a generated row was bought for ("make" rows).
            $table->foreignId('semi_ingredient_id')->nullable()->constrained('ingredients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('semi_ingredient_id');
        });

        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            $table->dropColumn('semi_choices');
        });

        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->dropColumn('semi_source');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipe_id');
            $table->dropColumn(['homemade_default', 'recipe_yield_amount', 'recipe_yield_unit']);
        });
    }
};
