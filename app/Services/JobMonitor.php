<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HasUserContext;
use App\Contracts\ShouldNotBeTracked;
use App\Events\JobCompleted as JobCompletedEvent;
use App\Events\JobFailed as JobFailedEvent;
use App\Events\JobStarted as JobStartedEvent;
use App\Models\JobRun;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class JobMonitor
{
    /**
     * Record the start of a job execution.
     */
    public function recordJobStart(JobProcessing $event): void
    {
        try {
            $job = $this->unserializeJob($event->job->payload());

            if ($this->shouldSkipJob($job)) {
                return;
            }

            $telemetry = $this->captureTelemetry();
            $jobUuid = $this->getJobUuid($event->job->payload());

            $jobRun = JobRun::create([
                'uuid' => $jobUuid,
                'user_id' => $this->extractUserId($job),
                'job_class' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'status' => 'processing',
                'payload' => $this->shouldStorePayload() ? $this->redactPayload($event->job->payload()) : null,
                'tags' => $this->extractTags($job),
                'started_at' => now(),
                'attempts' => $event->job->attempts(),
                'memory_start' => $telemetry['memory_current'] ?? null,
                'cpu_user' => $telemetry['cpu_user'] ?? null,
                'cpu_system' => $telemetry['cpu_system'] ?? null,
            ]);

            JobStartedEvent::dispatch($jobRun);
        } catch (Throwable $e) {
            // Silently fail to avoid breaking job execution
            report($e);
        }
    }

    /**
     * Record successful job completion.
     */
    public function recordJobSuccess(JobProcessed $event): void
    {
        try {
            $uuid = $this->getJobUuid($event->job->payload());
            $jobRun = JobRun::where('uuid', $uuid)->latest()->first();

            if (! $jobRun) {
                return;
            }

            $telemetry = $this->captureTelemetry();
            $finishedAt = now();

            $duration = null;
            if ($jobRun->started_at) {
                $duration = max(0, $jobRun->started_at->diffInMilliseconds($finishedAt));
            }

            $jobRun->update([
                'status' => 'processed',
                'finished_at' => $finishedAt,
                'duration_ms' => $duration,
                'memory_end' => $telemetry['memory_current'] ?? null,
                'memory_peak' => $telemetry['memory_peak'] ?? null,
            ]);

            JobCompletedEvent::dispatch($jobRun->fresh());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Record job failure.
     */
    public function recordJobFailure(JobFailed $event): void
    {
        try {
            $uuid = $this->getJobUuid($event->job->payload());
            $jobRun = JobRun::where('uuid', $uuid)->latest()->first();

            if (! $jobRun) {
                // Job was never recorded as processing, create a failure record
                $this->createFailureRecord($event);

                return;
            }

            $telemetry = $this->captureTelemetry();
            $finishedAt = now();

            $duration = null;
            if ($jobRun->started_at) {
                $duration = max(0, $jobRun->started_at->diffInMilliseconds($finishedAt));
            }

            $jobRun->update([
                'status' => 'failed',
                'finished_at' => $finishedAt,
                'duration_ms' => $duration,
                'exception' => get_class($event->exception),
                'stack_trace' => (string) $event->exception,
                'memory_end' => $telemetry['memory_current'] ?? null,
                'memory_peak' => $telemetry['memory_peak'] ?? null,
            ]);

            JobFailedEvent::dispatch($jobRun->fresh());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Create a failure record for a job that was never tracked as processing.
     */
    protected function createFailureRecord(JobFailed $event): void
    {
        $job = $this->unserializeJob($event->job->payload());

        if ($this->shouldSkipJob($job)) {
            return;
        }

        $telemetry = $this->captureTelemetry();

        $jobRun = JobRun::create([
            'uuid' => $this->getJobUuid($event->job->payload()),
            'job_class' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'status' => 'failed',
            'payload' => $this->shouldStorePayload() ? $this->redactPayload($event->job->payload()) : null,
            'tags' => $this->extractTags($job),
            'started_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 0,
            'attempts' => $event->job->attempts(),
            'exception' => get_class($event->exception),
            'stack_trace' => (string) $event->exception,
            'memory_start' => $telemetry['memory_current'] ?? null,
            'memory_end' => $telemetry['memory_current'] ?? null,
            'memory_peak' => $telemetry['memory_peak'] ?? null,
            'cpu_user' => $telemetry['cpu_user'] ?? null,
            'cpu_system' => $telemetry['cpu_system'] ?? null,
        ]);

        JobFailedEvent::dispatch($jobRun);
    }

    /**
     * Get or generate UUID for the job.
     */
    protected function getJobUuid(array $payload): string
    {
        return $payload['uuid'] ?? Str::uuid()->toString();
    }

    /**
     * Extract tags from the job instance.
     */
    protected function extractTags(mixed $job): ?array
    {
        if (! method_exists($job, 'tags')) {
            return null;
        }

        $tags = $job->tags();

        return is_array($tags) && count($tags) > 0 ? $tags : null;
    }

    /**
     * Extract user ID from the job instance.
     */
    protected function extractUserId(mixed $job): ?string
    {
        // Check if job implements HasUserContext interface
        if ($job instanceof HasUserContext) {
            return $job->getUserId();
        }

        // Check if job has a user property (legacy support)
        if (is_object($job) && property_exists($job, 'user') && $job->user) {
            return $job->user->id ?? null;
        }

        // Check if job has a user_id property
        if (is_object($job) && property_exists($job, 'user_id') && $job->user_id) {
            return $job->user_id;
        }

        // Fallback to authenticated user (works only if job is dispatched synchronously)
        return auth()->check() ? auth()->id() : null;
    }

    /**
     * Capture telemetry metrics.
     */
    protected function captureTelemetry(): array
    {
        if (! $this->telemetryEnabled()) {
            return [];
        }

        $telemetry = [
            'memory_current' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        if ($this->cpuTrackingEnabled()) {
            $usage = getrusage();
            $telemetry['cpu_user'] = $usage['ru_utime.tv_sec'] + ($usage['ru_utime.tv_usec'] / 1000000);
            $telemetry['cpu_system'] = $usage['ru_stime.tv_sec'] + ($usage['ru_stime.tv_usec'] / 1000000);
        }

        return $telemetry;
    }

    /**
     * Check if telemetry is enabled.
     */
    protected function telemetryEnabled(): bool
    {
        return config('job-monitoring.telemetry.enabled', true);
    }

    /**
     * Check if CPU tracking is enabled.
     */
    protected function cpuTrackingEnabled(): bool
    {
        return config('job-monitoring.telemetry.capture_cpu', true)
            && function_exists('getrusage');
    }

    /**
     * Check if payload storage is enabled.
     */
    protected function shouldStorePayload(): bool
    {
        return config('job-monitoring.store_payload', true);
    }

    /**
     * Redact sensitive keys from payload.
     */
    protected function redactPayload(array $payload): array
    {
        $redactKeys = config('job-monitoring.redact_keys', ['password', 'token', 'secret']);

        return $this->redactArray($payload, $redactKeys);
    }

    /**
     * Recursively redact keys from an array.
     */
    protected function redactArray(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactArray($value, $keys);
            } elseif ($this->shouldRedactKey($key, $keys)) {
                $data[$key] = '***REDACTED***';
            }
        }

        return $data;
    }

    /**
     * Check if a key should be redacted.
     */
    protected function shouldRedactKey(string $key, array $redactKeys): bool
    {
        foreach ($redactKeys as $redactKey) {
            if (stripos($key, $redactKey) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unserialize the job from payload.
     */
    protected function unserializeJob(array $payload): mixed
    {
        return unserialize($payload['data']['command'] ?? '');
    }

    /**
     * Check if this job should be skipped from monitoring.
     */
    protected function shouldSkipJob(mixed $job): bool
    {
        if (! is_object($job)) {
            return true;
        }

        // Skip broadcasting events to avoid infinite loops
        if ($job instanceof ShouldBroadcast ||
            $job instanceof ShouldBroadcastNow) {
            return true;
        }

        $excludedJobs = config('job-monitoring.exclude_jobs', []);

        foreach ($excludedJobs as $excludedJob) {
            if ($job instanceof $excludedJob) {
                return true;
            }
        }

        // Skip jobs explicitly marked as untracked
        if ($job instanceof ShouldNotBeTracked) {
            return true;
        }

        return false;
    }

    /**
     * Retry a failed job.
     */
    public function retryFailedJob(string $jobRunId): ?JobRun
    {
        $jobRun = JobRun::findOrFail($jobRunId);

        if ($jobRun->status !== 'failed') {
            throw new \InvalidArgumentException('Only failed jobs can be retried');
        }

        if (! $jobRun->payload) {
            throw new \InvalidArgumentException('Cannot retry job without stored payload');
        }

        $job = $this->unserializeJob($jobRun->payload);

        $retryJobRun = JobRun::create([
            'uuid' => Str::uuid()->toString(),
            'job_class' => $jobRun->job_class,
            'queue' => $jobRun->queue,
            'connection' => $jobRun->connection,
            'status' => 'processing',
            'payload' => $jobRun->payload,
            'tags' => $jobRun->tags,
            'started_at' => now(),
            'attempts' => 0,
            'original_job_run_id' => $jobRun->id,
        ]);

        dispatch($job)->onQueue($jobRun->queue)->onConnection($jobRun->connection);

        return $retryJobRun;
    }

    /**
     * Get queue depth for all queues or a specific queue.
     */
    public function getQueueDepth(?string $queue = null): array
    {
        $query = DB::table('jobs');

        if ($queue) {
            $query->where('queue', $queue);
        }

        return $query
            ->select('queue', DB::raw('count(*) as count'))
            ->groupBy('queue')
            ->get()
            ->map(function ($item) {
                return [
                    'queue' => $item->queue,
                    'count' => (int) $item->count,
                    'status' => $this->getQueueHealthStatus((int) $item->count),
                ];
            })
            ->all();
    }

    /**
     * Get health status for a queue based on depth.
     */
    protected function getQueueHealthStatus(int $count): string
    {
        $thresholds = config('job-monitoring.queue_depth_thresholds', [
            'healthy' => 10,
            'warning' => 50,
            'critical' => 100,
        ]);

        if ($count < $thresholds['healthy']) {
            return 'healthy';
        }

        if ($count < $thresholds['warning']) {
            return 'normal';
        }

        if ($count < $thresholds['critical']) {
            return 'warning';
        }

        return 'critical';
    }
}
