<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shopping list items get a real date (`needed_on`) instead of a bare week
 * day name, and `checked_at` so "bought" can be tracked independently of
 * other edits touching updated_at.
 */
return new class extends Migration
{
    private const WEEK_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->date('needed_on')->nullable()->after('notes')->index();
            $table->timestamp('checked_at')->nullable()->after('is_checked');
        });

        $weekStart = Carbon::now()->startOfWeek();

        DB::table('shopping_list_items')->orderBy('id')->each(function ($item) use ($weekStart) {
            $neededOn = null;

            if ($item->meal_id) {
                $mealDate = DB::table('meals')->where('id', $item->meal_id)->value('date');
                $neededOn = $mealDate ? Carbon::parse($mealDate)->toDateString() : null;
            }

            if (! $neededOn && $item->week_day) {
                $index = array_search($item->week_day, self::WEEK_DAYS, true);
                $neededOn = $index === false ? null : $weekStart->copy()->addDays($index)->toDateString();
            }

            DB::table('shopping_list_items')->where('id', $item->id)->update([
                'needed_on' => $neededOn,
                'checked_at' => $item->is_checked ? $item->updated_at : null,
            ]);
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('week_day');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->enum('week_day', self::WEEK_DAYS)->nullable();
        });

        DB::table('shopping_list_items')->whereNotNull('needed_on')->orderBy('id')->each(function ($item) {
            DB::table('shopping_list_items')->where('id', $item->id)->update([
                'week_day' => strtolower(Carbon::parse($item->needed_on)->englishDayOfWeek),
            ]);
        });

        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropIndex(['needed_on']);
            $table->dropColumn(['needed_on', 'checked_at']);
        });
    }
};
