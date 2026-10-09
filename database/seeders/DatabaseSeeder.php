<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@geoma.local',
                'password' => bcrypt('password'),
            ]
        );

        User::firstOrCreate(
            ['username' => 'daffa'],
            [
                'name' => 'Daffa',
                'email' => 'daffa@geoma.local',
                'password' => bcrypt('daffa123'),
            ]
        );
    }
}
