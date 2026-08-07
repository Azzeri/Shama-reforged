<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The 2026_07_22_162130_clean_up_tags migration merged Polish/English tag
     * duplicates once, but RecipeCatalogSeeder kept recreating the Polish
     * names (raw 'obiad', 'kolacja', 'deser', 'sniadanie') on every reseed
     * until it was fixed to use English names directly. This re-runs the
     * same merge so any environment (including prod) that still has those
     * Polish duplicates gets cleaned up too. Safe to run even if there's
     * nothing left to merge.
     */
    public function up(): void
    {
        $dictionary = [
            'deser' => 'Dessert',
            'kolacja' => 'Supper',
            'lunch' => 'Lunch',
            'obiad' => 'Dinner',
            'sniadanie' => 'Breakfast',
            'śniadanie' => 'Breakfast',
            'kurczak' => 'Chicken',
            'owoce morza' => 'Seafood',
            'ryba' => 'Fish',
            'wieprzowina' => 'Pork',
            'wołowina' => 'Beef',
            'wege' => 'Vegetarian',
        ];

        $tags = DB::table('tags')->get();

        $groupedByTargetName = [];

        foreach ($tags as $tag) {
            $normalizedOriginal = mb_strtolower(trim($tag->name));
            $targetName = $dictionary[$normalizedOriginal] ?? Str::ucfirst($normalizedOriginal);

            $groupedByTargetName[$targetName][] = $tag->id;
        }

        DB::transaction(function () use ($groupedByTargetName) {
            foreach ($groupedByTargetName as $targetName => $tagIds) {
                if (count($tagIds) === 1) {
                    DB::table('tags')->where('id', $tagIds[0])->update(['name' => $targetName]);

                    continue;
                }

                $primaryTagId = array_shift($tagIds);

                DB::table('tags')->where('id', $primaryTagId)->update(['name' => $targetName]);

                foreach ($tagIds as $duplicateTagId) {
                    $assignments = DB::table('recipe_tag_assignments')
                        ->where('tag_id', $duplicateTagId)
                        ->get();

                    foreach ($assignments as $assignment) {
                        $alreadyHasPrimary = DB::table('recipe_tag_assignments')
                            ->where('recipe_id', $assignment->recipe_id)
                            ->where('tag_id', $primaryTagId)
                            ->exists();

                        if ($alreadyHasPrimary) {
                            DB::table('recipe_tag_assignments')->where('id', $assignment->id)->delete();
                        } else {
                            DB::table('recipe_tag_assignments')->where('id', $assignment->id)->update(['tag_id' => $primaryTagId]);
                        }
                    }

                    DB::table('tags')->where('id', $duplicateTagId)->delete();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
