<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@elive.co.tz'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'eventadmin@elive.co.tz'],
            [
                'name' => 'Event Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_EVENT_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'checkin@elive.co.tz'],
            [
                'name' => 'Check-in Officer',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CHECK_IN_OFFICER,
            ]
        );
    }
}