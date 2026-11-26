<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redesign: a recipe is written for a number of servings (one serving =
     * one person) instead of per-person amounts; recipes can be archived and
     * get two new tags — a health rating and "good for packing".
     * Existing recipes were written for the two of us, hence 2 servings.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedSmallInteger('servings')->default(2)->after('link');
            $table->string('rating', 16)->nullable()->after('servings');
            $table->boolean('packable')->default(false)->after('rating');
            $table->timestamp('archived_at')->nullable()->after('packable');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['servings', 'rating', 'packable', 'archived_at']);
        });
    }
};
