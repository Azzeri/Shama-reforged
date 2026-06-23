<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('category')->default('meal_type')->after('name');
        });

        // Update existing meal type tags to ensure they are marked as meal_type
        $mealTypeTags = ['sniadanie', 'lunch', 'obiad', 'kolacja', 'deser'];
        DB::table('tags')->whereIn('name', $mealTypeTags)->update(['category' => 'meal_type']);

        // Add new diet type tags
        $now = now();
        $dietTypeTags = [
            'wieprzowina',
            'kurczak',
            'wołowina',
            'wege',
            'ryba',
            'owoce morza',
        ];

        foreach ($dietTypeTags as $tagName) {
            DB::table('tags')->updateOrInsert(
                ['name' => $tagName],
                [
                    'category' => 'diet_type',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove diet type tags
        DB::table('tags')->where('category', 'diet_type')->delete();

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
