<?php

namespace Database\Factories;

use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EconomicTransactionAttachment>
 */
class EconomicTransactionAttachmentFactory extends Factory
{
    protected $model = EconomicTransactionAttachment::class;

    /**
     * Define the default state of a transaction attachment.
     *
     * @return array<string, mixed> The default model attributes.
     */
    public function definition(): array
    {
        $filename = $this->faker->word().'.jpg';

        return [
            'transaction_id' => EconomicTransaction::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'economy/attachments/'.Str::uuid().'.jpg',
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size' => $this->faker->numberBetween(1000, 500000),
            'sha256' => hash('sha256', $filename.$this->faker->uuid()),
            'sort_order' => 0,
        ];
    }
}
