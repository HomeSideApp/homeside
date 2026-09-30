<?php

namespace Database\Factories;

use App\Enums\TranslationEntityStatus;
use App\Models\Category;
use App\Models\TranslationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationStatus>
 */
class TranslationStatusFactory extends Factory
{
    protected $model = TranslationStatus::class;

    public function definition(): array
    {
        return [
            'translatable_type' => 'category',
            'translatable_id' => Category::factory(),
            'locale' => 'es-ES',
            'status' => TranslationEntityStatus::Incomplete->value,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TranslationEntityStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function complete(): static
    {
        return $this->state(fn () => ['status' => TranslationEntityStatus::Complete->value]);
    }
}
