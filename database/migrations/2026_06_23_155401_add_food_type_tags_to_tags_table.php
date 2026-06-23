<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        $newTags = [
            'wieprzowina',
            'kurczak',
            'wołowina',
            'wege',
            'ryba',
            'owoce morza',
        ];

        foreach ($newTags as $tagName) {
            DB::table('tags')->insertOrIgnore([
                'name' => $tagName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tags')->whereIn('name', [
            'wieprzowina',
            'kurczak',
            'wołowina',
            'wege',
            'ryba',
            'owoce morza',
        ])->delete();
    }
};
