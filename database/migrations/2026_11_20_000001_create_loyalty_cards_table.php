<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store loyalty cards, shared by the household (like the shopping list).
        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            $table->string('store');
            $table->string('number');
            $table->string('code_type')->default('barcode'); // barcode | qr
            $table->string('format')->nullable();            // e.g. EAN13, CODE128 — as scanned
            $table->string('color')->default('terracotta');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_cards');
    }
};
