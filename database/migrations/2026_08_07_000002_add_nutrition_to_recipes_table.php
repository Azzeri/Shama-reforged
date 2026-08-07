<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->unsignedSmallInteger('calories_me')->nullable();
            $table->decimal('protein_me', 6, 1)->nullable();
            $table->decimal('fat_me', 6, 1)->nullable();
            $table->decimal('carbs_me', 6, 1)->nullable();

            $table->unsignedSmallInteger('calories_wife')->nullable();
            $table->decimal('protein_wife', 6, 1)->nullable();
            $table->decimal('fat_wife', 6, 1)->nullable();
            $table->decimal('carbs_wife', 6, 1)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn([
                'calories_me', 'protein_me', 'fat_me', 'carbs_me',
                'calories_wife', 'protein_wife', 'fat_wife', 'carbs_wife',
            ]);
        });
    }
};
