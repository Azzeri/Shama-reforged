<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recipe that is only a semi-product (a sauce, a dough, a stock): it is
     * used through an ingredient in other recipes, never planned as a meal,
     * and needs none of the meal labels.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('is_semi_product')->default(false)->after('packable');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('is_semi_product');
        });
    }
};
