<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingredient amounts differ per person's portion (Mariusz/Natalia), so a
     * single shared `amount` can't represent both. Shopping-list totals are
     * computed by summing amount_me + amount_wife at generation time.
     */
    public function up(): void
    {
        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->decimal('amount_me', 8, 2)->nullable()->after('quantity');
            $table->decimal('amount_wife', 8, 2)->nullable()->after('amount_me');
        });

        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->dropColumn(['amount_me', 'amount_wife']);
            $table->decimal('amount', 8, 2)->nullable();
        });
    }
};
