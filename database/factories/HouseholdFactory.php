<?php

namespace Database\Factories;

use App\Enums\HouseholdModule;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word().' '.fake()->word(),
            'invite_code' => strtoupper(Str::random(8)),
            'settings' => null,
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Household $household) {
            foreach (HouseholdModule::cases() as $module) {
                $household->modules()->create([
                    'module' => $module->value,
                    'enabled' => true,
                ]);
            }
        });
    }
}
