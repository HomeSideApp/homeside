<?php

namespace Database\Factories;

use App\Models\EconomicDocument;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomicDocument>
 */
class EconomicDocumentFactory extends Factory
{
    protected $model = EconomicDocument::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'economy/documents/'.$this->faker->uuid().'.jpg',
            'original_filename' => 'ticket.jpg',
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(10000, 500000),
            'sha256' => hash('sha256', $this->faker->unique()->uuid()),
            'ai_path' => null,
        ];
    }

    public function forHousehold(Household $household): static
    {
        return $this->state(fn () => ['household_id' => $household->id]);
    }

    public function uploadedBy(User $user): static
    {
        return $this->state(fn () => ['uploaded_by' => $user->id]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['household_id' => null]);
    }
}
