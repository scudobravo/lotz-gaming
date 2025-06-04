<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'MA',
                'description' => 'Master Admin - Controllo assoluto su tutto il sistema'
            ],
            [
                'name' => 'SA',
                'description' => 'Super Admin - Può vedere tutti i progetti'
            ],
            [
                'name' => 'A',
                'description' => 'Admin - Può vedere solo i propri progetti'
            ]
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
