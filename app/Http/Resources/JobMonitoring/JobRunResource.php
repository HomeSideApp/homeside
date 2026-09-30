<?php

declare(strict_types=1);

namespace App\Http\Resources\JobMonitoring;

use App\Models\JobRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read JobRun $resource
 */
class JobRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'job_class' => $this->resource->short_job_class,
            'full_job_class' => $this->resource->job_class,
            'queue' => $this->resource->queue,
            'connection' => $this->resource->connection,
            'status' => $this->resource->status,
            'duration' => $this->resource->formatted_duration,
            'duration_ms' => $this->resource->duration_ms,
            'attempts' => $this->resource->attempts,
            'exception' => $this->resource->exception,
            'stack_trace' => $this->resource->stack_trace,
            'memory_start' => $this->resource->memory_start,
            'memory_end' => $this->resource->memory_end,
            'memory_peak' => $this->resource->memory_peak,
            'memory_usage' => $this->resource->formatted_memory_usage,
            'cpu_user' => $this->resource->cpu_user,
            'cpu_system' => $this->resource->cpu_system,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->resource->user->id,
                'name' => $this->resource->user->name,
            ]),
            'tags' => $this->resource->tags,
            'payload' => $this->resource->payload,
            'started_at' => $this->resource->started_at?->toDateTimeString(),
            'finished_at' => $this->resource->finished_at?->toDateTimeString(),
            'created_at' => $this->resource->created_at?->toDateTimeString(),
            'original_job_run_id' => $this->resource->original_job_run_id,
            'retries' => JobRunResource::collection($this->whenLoaded('retries')),
        ];
    }
}
