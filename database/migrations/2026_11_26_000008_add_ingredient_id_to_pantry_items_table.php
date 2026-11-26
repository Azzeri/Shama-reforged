<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A household product can be "the same product as" a recipe ingredient:
     * while it is in stock, recipes don't put it on the shopping list as a
     * normal item ("Pewnie masz w domu"); when low / out, it is one row.
     */
    public function up(): void
    {
        Schema::table('pantry_items', function (Blueprint $table) {
            $table->foreignId('ingredient_id')->nullable()->after('pantry_group_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pantry_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingredient_id');
        });
    }
};
