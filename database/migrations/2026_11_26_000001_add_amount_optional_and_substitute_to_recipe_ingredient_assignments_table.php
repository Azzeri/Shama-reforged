<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One amount for the whole recipe (all servings) instead of separate
     * amounts per person. The amount = Mariusz's + Natalia's amount; the old
     * per-person columns stay untouched so this can be reverted.
     * Plus: optional ingredients and a free-text substitute ("lub: hummus").
     */
    public function up(): void
    {
        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->nullable()->after('quantity');
            $table->boolean('is_optional')->default(false)->after('unit');
            $table->string('substitute')->nullable()->after('is_optional');
        });

        DB::table('recipe_ingredient_assignments')
            ->where(fn ($query) => $query->whereNotNull('amount_me')->orWhereNotNull('amount_wife'))
            ->update(['amount' => DB::raw('COALESCE(amount_me, 0) + COALESCE(amount_wife, 0)')]);
    }

    public function down(): void
    {
        Schema::table('recipe_ingredient_assignments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'is_optional', 'substitute']);
        });
    }
};
