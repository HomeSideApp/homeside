<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JobRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobRun>
 */
class JobRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<JobRun>
     */
    protected $model = JobRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['processing', 'processed', 'failed']);
        $startedAt = $this->faker->dateTimeBetween('-1 day');

        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => null,
            'job_class' => 'App\\Jobs\\'.Str::studly($this->faker->unique()->words(2, true)),
            'queue' => 'default',
            'connection' => 'database',
            'status' => $status,
            'payload' => null,
            'exception' => $status === 'failed' ? 'RuntimeException' : null,
            'stack_trace' => $status === 'failed' ? 'RuntimeException: something went wrong' : null,
            'tags' => null,
            'started_at' => $startedAt,
            'finished_at' => $status === 'processing' ? null : $this->faker->dateTimeBetween($startedAt),
            'duration_ms' => $status === 'processing' ? null : $this->faker->numberBetween(1, 5000),
            'attempts' => $this->faker->numberBetween(0, 3),
            'memory_start' => $this->faker->numberBetween(1024, 1048576),
            'memory_end' => $this->faker->numberBetween(1024, 1048576),
            'memory_peak' => $this->faker->numberBetween(1048576, 16777216),
            'cpu_user' => $this->faker->randomFloat(3, 0, 2),
            'cpu_system' => $this->faker->randomFloat(3, 0, 1),
            'original_job_run_id' => null,
        ];
    }

    /**
     * Indicate that the job run is still processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'finished_at' => null,
            'duration_ms' => null,
            'exception' => null,
            'stack_trace' => null,
        ]);
    }

    /**
     * Indicate that the job run finished successfully.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'exception' => null,
            'stack_trace' => null,
        ]);
    }

    /**
     * Indicate that the job run failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'exception' => 'RuntimeException',
            'stack_trace' => 'RuntimeException: something went wrong',
        ]);
    }
}
