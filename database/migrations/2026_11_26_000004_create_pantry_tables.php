<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Household supplies (kitchen, bathroom, cleaning...) with a simple stock
     * state. "Low" / "out" puts the product on the shopping list. Shared by
     * the household, like the shopping list.
     */
    public function up(): void
    {
        Schema::create('pantry_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('pantry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pantry_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 8)->default('ok');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pantry_items');
        Schema::dropIfExists('pantry_groups');
    }
};
