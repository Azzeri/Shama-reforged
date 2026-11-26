<?php

use App\Support\Ingredients\IngredientName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingredient names are unique regardless of case and Polish diacritics
     * ("Pomidor" = "pomidor" = "Pomidór"). name_key holds the folded name and
     * carries the unique index. Rows that already clash keep their own key
     * with the id appended (no data is touched) — merge them on the
     * Ingredients screen ("Possible duplicates").
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('name_key')->nullable()->after('name');
        });

        $seen = [];

        foreach (DB::table('ingredients')->orderBy('id')->get(['id', 'name']) as $row) {
            $key = IngredientName::fold($row->name);
            $key = isset($seen[$key]) ? "{$key}#{$row->id}" : $key;
            $seen[$key] = true;

            DB::table('ingredients')->where('id', $row->id)->update(['name_key' => $key]);
        }

        Schema::table('ingredients', function (Blueprint $table) {
            $table->unique('name_key');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropUnique(['name_key']);
            $table->dropColumn('name_key');
        });
    }
};
