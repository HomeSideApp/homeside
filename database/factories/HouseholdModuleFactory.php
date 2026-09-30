<?php

namespace Database\Factories;

use App\Enums\HouseholdModule;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\HouseholdModule>
 */
class HouseholdModuleFactory extends Factory
{
    protected $model = \App\Models\HouseholdModule::class;

    public function definition(): array
    {
        $modules = HouseholdModule::cases();

        return [
            'household_id' => Household::factory(),
            'module' => $this->faker->randomElement($modules)->value,
            'enabled' => true,
            'settings' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn () => ['enabled' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function forModule(HouseholdModule $module): static
    {
        return $this->state(fn () => ['module' => $module->value]);
    }
}
