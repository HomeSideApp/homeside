<?php

namespace Database\Seeders;

use App\Enums\HouseholdTagType;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class HouseholdTagSeeder extends Seeder
{
    /**
     * Tags predefinidos para clasificar hogares.
     *
     * @var array<string, string>
     */
    private const PREDEFINED_TAGS = [
        'Familia' => 'familia',
        'Compañeros de piso' => 'roommates',
        'Pareja' => 'pareja',
        'Individual' => 'individual',
        'Compartido' => 'compartido',
    ];

    public function run(): void
    {
        foreach (self::PREDEFINED_TAGS as $name => $slug) {
            Tag::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'type' => HouseholdTagType::Predefined->value,
                ]
            );
        }
    }
}
