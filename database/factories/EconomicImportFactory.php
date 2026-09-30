<?php

namespace Database\Factories;

use App\Enums\EconomicImportStatus;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomicImport>
 */
class EconomicImportFactory extends Factory
{
    protected $model = EconomicImport::class;

    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'document_id' => EconomicDocument::factory(),
            'created_by' => User::factory(),
            'status' => EconomicImportStatus::Pending,
            'requested_sections' => ['place' => true, 'date' => true, 'items' => true, 'taxes' => true],
        ];
    }

    public function forHousehold(Household $household): static
    {
        return $this->state(fn () => ['household_id' => $household->id]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => ['created_by' => $user->id]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['household_id' => null]);
    }

    public function status(EconomicImportStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function extracted(array $payload): static
    {
        return $this->state(fn () => [
            'status' => EconomicImportStatus::ReadyForReview,
            'extracted_payload' => $payload,
            'finished_at' => now(),
        ]);
    }
}
