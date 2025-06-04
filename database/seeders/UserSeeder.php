<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea il Master Admin
        $masterAdmin = User::create([
            'name' => 'Master Admin',
            'email' => 'master@lotzapp.com',
            'password' => Hash::make('password'),
        ]);
        $masterAdmin->roles()->attach(Role::where('name', 'master_admin')->first());

        // Crea il Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@lotzapp.com',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->roles()->attach(Role::where('name', 'super_admin')->first());

        // Crea l'Admin
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@lotzapp.com',
            'password' => Hash::make('password'),
        ]);
        $admin->roles()->attach(Role::where('name', 'admin')->first());
    }
}
