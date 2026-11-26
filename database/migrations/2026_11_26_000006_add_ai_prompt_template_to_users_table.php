<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per-user AI meal plan prompt; null = the default template from the code. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_prompt_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ai_prompt_template');
        });
    }
};
