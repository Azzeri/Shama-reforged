<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Have it ready": a planned recipe that needs no shopping (e.g. a
     * portion from the freezer). It stays in the plan like any other meal,
     * only its ingredients are left off the shopping list.
     */
    public function up(): void
    {
        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            $table->boolean('skip_shopping')->default(false)->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            $table->dropColumn('skip_shopping');
        });
    }
};
