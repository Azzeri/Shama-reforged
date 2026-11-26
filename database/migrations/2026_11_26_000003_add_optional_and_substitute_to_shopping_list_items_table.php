<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Copied from the recipe when the list is generated, shown next to the item. */
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('unit');
            $table->string('substitute')->nullable()->after('is_optional');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn(['is_optional', 'substitute']);
        });
    }
};
