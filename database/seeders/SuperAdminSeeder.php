<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@elive.co.tz')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'Admin@123##'),
                'role' => 'super_admin',
            ]
        );
    }
}