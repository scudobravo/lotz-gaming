<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Master Admin
        $masterAdmin = User::create([
            'name' => 'Master Admin',
            'email' => 'master@lotzapp.com',
            'password' => Hash::make('password123'),
        ]);
        $masterAdmin->roles()->attach(Role::where('name', 'MA')->first());

        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@lotzapp.com',
            'password' => Hash::make('password123'),
        ]);
        $superAdmin->roles()->attach(Role::where('name', 'SA')->first());

        // Admin
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@lotzapp.com',
            'password' => Hash::make('password123'),
        ]);
        $admin->roles()->attach(Role::where('name', 'A')->first());
    }
}
