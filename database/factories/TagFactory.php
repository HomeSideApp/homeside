<?php

namespace Database\Factories;

use App\Enums\HouseholdTagType;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => HouseholdTagType::Custom->value,
        ];
    }

    public function predefined(): static
    {
        return $this->state(fn () => ['type' => HouseholdTagType::Predefined->value]);
    }

    public function custom(): static
    {
        return $this->state(fn () => ['type' => HouseholdTagType::Custom->value]);
    }
}
