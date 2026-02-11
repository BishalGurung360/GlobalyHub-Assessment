<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the users table with John Doe and Jane Doe.
     * Uses updateOrCreate so the seeder is idempotent.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'john@doe.com'],
            [
                'name' => 'John Doe',
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'jane@doe.com'],
            [
                'name' => 'Jane Doe',
                'password' => 'password',
            ]
        );
    }
}
