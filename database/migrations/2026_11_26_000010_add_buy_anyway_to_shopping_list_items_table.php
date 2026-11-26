<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Kupię jednak": a recipe ingredient that is probably at home (linked
     * household product in stock) goes to its normal aisle anyway — only on
     * the current list, the household product stays as it is.
     */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->boolean('buy_anyway')->default(false)->after('substitute');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('buy_anyway');
        });
    }
};
