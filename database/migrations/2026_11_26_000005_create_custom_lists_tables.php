<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopping lists independent of the meal plan (hardware store, party...).
     * Kept in the DB so both phones see them, like the loyalty cards.
     */
    public function up(): void
    {
        Schema::create('custom_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('custom_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_list_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_list_items');
        Schema::dropIfExists('custom_lists');
    }
};
