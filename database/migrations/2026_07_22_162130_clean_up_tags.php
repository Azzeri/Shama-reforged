<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Słownik mapowania polskich nazw (małe litery) na angielskie (z Wielkiej litery)
        $dictionary = [
            'deser'            => 'Dessert',
            'kolacja'          => 'Supper',
            'lunch'          => 'Lunch',
            'obiad'            => 'Dinner',
            'sniadanie'        => 'Breakfast',
            'śniadanie'        => 'Breakfast',
            'kurczak'          => 'Chicken',
            'owoce morza'      => 'Seafood',
            'ryba'             => 'Fish',
            'wieprzowina'        => 'Pork',
            'wołowina'         => 'Beef',
            'wege'             => 'Vegetarian',
        ];

        $tags = DB::table('tags')->get();

        // Grupujemy istniejące ID tagów według ich nowej, angielskiej nazwy
        $groupedByTargetName = [];

        foreach ($tags as $tag) {
            $normalizedOriginal = mb_strtolower(trim($tag->name));

            // Jeśli jest w słowniku, bierzemy tłumaczenie. 
            // Jeśli nie, robimy pierwszą litery wielką (np. "makaron" -> "Makaron")
            $targetName = $dictionary[$normalizedOriginal] ?? Str::ucfirst($normalizedOriginal);

            $groupedByTargetName[$targetName][] = $tag->id;
        }

        // 2. Scalanie tagów i bezpieczna aktualizacja bazy
        DB::transaction(function () use ($groupedByTargetName) {
            foreach ($groupedByTargetName as $targetName => $tagIds) {
                // Wybieramy pierwsze ID jako główny tag
                $primaryTagId = array_shift($tagIds);

                // Zmieniamy nazwę głównego tagu na nową
                DB::table('tags')
                    ->where('id', $primaryTagId)
                    ->update(['name' => $targetName]);

                // Jeśli istniały duplikaty (np. 'sniadanie' i 'śniadanie'), scalamy je z głównym
                foreach ($tagIds as $duplicateTagId) {
                    $assignments = DB::table('recipe_tag_assignments')
                        ->where('tag_id', $duplicateTagId)
                        ->get();

                    foreach ($assignments as $assignment) {
                        // Sprawdzamy czy przepis ma już przypisany główny tag (żeby nie złamać unique constraint)
                        $alreadyHasPrimary = DB::table('recipe_tag_assignments')
                            ->where('recipe_id', $assignment->recipe_id)
                            ->where('tag_id', $primaryTagId)
                            ->exists();

                        if ($alreadyHasPrimary) {
                            // Przepis ma już ten tag -> usuwamy nadmiarowe przypisanie
                            DB::table('recipe_tag_assignments')
                                ->where('id', $assignment->id)
                                ->delete();
                        } else {
                            // Przepis nie ma jeszcze głównego tagu -> przepinamy na główny
                            DB::table('recipe_tag_assignments')
                                ->where('id', $assignment->id)
                                ->update(['tag_id' => $primaryTagId]);
                        }
                    }

                    // Usuwamy zbędny duplikat tagu z tabeli 'tags'
                    DB::table('tags')
                        ->where('id', $duplicateTagId)
                        ->delete();
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
