<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'identifier' => 'A',
                'name' => 'Lettera A',
                'description' => 'La prima lettera dell\'alfabeto',
                'image_url' => null
            ],
            [
                'identifier' => 'B',
                'name' => 'Lettera B',
                'description' => 'La seconda lettera dell\'alfabeto',
                'image_url' => null
            ],
            [
                'identifier' => 'C',
                'name' => 'Lettera C',
                'description' => 'La terza lettera dell\'alfabeto',
                'image_url' => null
            ],
            [
                'identifier' => 'TT',
                'name' => 'Tipo Tipografico',
                'description' => 'Un carattere tipografico antico',
                'image_url' => null
            ],
            [
                'identifier' => 'CHIAVE_1',
                'name' => 'Chiave Antica',
                'description' => 'Una chiave antica per aprire un forziere',
                'image_url' => null
            ]
        ];

        foreach ($items as $item) {
            Item::create($item);
        }
    }
} 