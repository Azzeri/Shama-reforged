<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "For packing" gets a third state: not set (null). Until now "no" and
     * "not set" were the same false, so existing false becomes null and those
     * recipes show up on the "to fill in" page.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('packable')->nullable()->default(null)->change();
        });

        DB::table('recipes')->where('packable', false)->update(['packable' => null]);
    }

    public function down(): void
    {
        DB::table('recipes')->whereNull('packable')->update(['packable' => false]);

        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('packable')->default(false)->nullable(false)->change();
        });
    }
};
