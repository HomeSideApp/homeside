<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\JobRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public JobRun $jobRun)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('job-monitoring'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->jobRun->loadMissing(['user:id,name']);

        return [
            'id' => $this->jobRun->id,
            'uuid' => $this->jobRun->uuid,
            'job_class' => $this->jobRun->short_job_class,
            'full_job_class' => $this->jobRun->job_class,
            'queue' => $this->jobRun->queue,
            'connection' => $this->jobRun->connection,
            'status' => $this->jobRun->status,
            'duration' => $this->jobRun->formatted_duration,
            'duration_ms' => $this->jobRun->duration_ms,
            'attempts' => $this->jobRun->attempts,
            'started_at' => $this->jobRun->started_at?->toDateTimeString(),
            'finished_at' => $this->jobRun->finished_at?->toDateTimeString(),
            'memory_start' => $this->jobRun->memory_start,
            'memory_end' => $this->jobRun->memory_end,
            'memory_peak' => $this->jobRun->memory_peak,
            'cpu_user' => $this->jobRun->cpu_user,
            'cpu_system' => $this->jobRun->cpu_system,
            'user' => $this->jobRun->user ? [
                'id' => $this->jobRun->user->id,
                'name' => $this->jobRun->user->name,
            ] : null,
            'tags' => $this->jobRun->tags,
            'created_at' => $this->jobRun->created_at->diffForHumans(),
        ];
    }
}
