<?php

namespace Database\Factories;

use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'translatable_type' => 'category',
            'translatable_id' => Category::factory(),
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => $this->faker->unique()->word(),
            'status' => TranslationFieldStatus::Pending->value,
        ];
    }

    public function translated(): static
    {
        return $this->state(fn () => ['status' => TranslationFieldStatus::Translated->value]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => TranslationFieldStatus::Approved->value]);
    }
}
