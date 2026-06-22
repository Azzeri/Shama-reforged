<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RecipeCatalogSeeder::class);

        User::query()->updateOrCreate(
            ['email' => 'artess2698@gmail.com'],
            [
                'name' => 'Mariusz',
                'password' => Hash::make('JHDFS*(!*@#HSANkasdhau123'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'natalia98.8@wp.pl'],
            [
                'name' => 'Natalia',
                'password' => Hash::make('temp'),
            ]
        );

    }
}
