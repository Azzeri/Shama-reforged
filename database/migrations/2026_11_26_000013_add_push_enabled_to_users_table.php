<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Account-wide switch: off = no push notifications on any of the user's devices. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_enabled');
        });
    }
};
