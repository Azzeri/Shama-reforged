<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            // Always at home (salt, water, ...): never put on the shopping list.
            $table->boolean('skip_shopping')->default(false)->after('purchase_timing');
        });

        DB::table('ingredients')->whereIn('name', ['Sól', 'Woda'])->update(['skip_shopping' => true]);
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('skip_shopping');
        });
    }
};
