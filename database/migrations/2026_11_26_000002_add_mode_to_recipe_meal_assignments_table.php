<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recipe in the plan is either one meal ("single", the default) or
     * cooked whole ("full") — then the following days can get "leftover"
     * entries that point back to the cooking day and need no shopping.
     * Leftovers go away with the cooking entry.
     */
    public function up(): void
    {
        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            $table->string('mode', 16)->default('single')->after('meal_id');
            $table->foreignId('cooked_from_id')->nullable()->after('mode')
                ->constrained('recipe_meal_assignments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recipe_meal_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cooked_from_id');
            $table->dropColumn('mode');
        });
    }
};
