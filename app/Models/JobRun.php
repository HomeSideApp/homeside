<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * JobRun Model
 *
 * Represents a tracked job execution in the queue monitoring system.
 *
 * @property string $id
 * @property string $uuid
 * @property string|null $user_id
 * @property string $job_class
 * @property string $queue
 * @property string $connection
 * @property string $status
 * @property array|null $payload
 * @property string|null $exception
 * @property string|null $stack_trace
 * @property array|null $tags
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property int $attempts
 * @property int|null $memory_start
 * @property int|null $memory_end
 * @property int|null $memory_peak
 * @property float|null $cpu_user
 * @property float|null $cpu_system
 * @property string|null $original_job_run_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read JobRun|null $originalJobRun
 * @property-read Collection|JobRun[] $retries
 */
class JobRun extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'job_runs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'job_class',
        'queue',
        'connection',
        'status',
        'payload',
        'exception',
        'stack_trace',
        'tags',
        'started_at',
        'finished_at',
        'duration_ms',
        'attempts',
        'memory_start',
        'memory_end',
        'memory_peak',
        'cpu_user',
        'cpu_system',
        'original_job_run_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'tags' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
            'memory_start' => 'integer',
            'memory_end' => 'integer',
            'memory_peak' => 'integer',
            'cpu_user' => 'float',
            'cpu_system' => 'float',
        ];
    }

    /**
     * Get the user who initiated this job run.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the original job run if this is a retry.
     */
    public function originalJobRun(): BelongsTo
    {
        return $this->belongsTo(JobRun::class, 'original_job_run_id');
    }

    /**
     * Get all retry attempts for this job.
     */
    public function retries(): HasMany
    {
        return $this->hasMany(JobRun::class, 'original_job_run_id');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by queue.
     */
    public function scopeByQueue(Builder $query, string $queue): Builder
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope a query to filter by job class.
     */
    public function scopeByJobClass(Builder $query, string $jobClass): Builder
    {
        return $query->where('job_class', 'like', "%{$jobClass}%");
    }

    /**
     * Scope a query to jobs carrying any (or all) of the given tags.
     */
    public function scopeWithTags(Builder $query, array $tags, string $mode = 'any'): Builder
    {
        if (empty($tags)) {
            return $query;
        }

        if ($mode === 'all') {
            foreach ($tags as $tag) {
                $query->whereRaw('JSON_CONTAINS(tags, ?)', [json_encode(['tag' => $tag])]);
            }

            return $query;
        }

        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereRaw('JSON_CONTAINS(tags, ?)', [json_encode(['tag' => $tag])]);
            }
        });
    }

    /**
     * Scope a query to only failed jobs.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only processed jobs.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope a query to only processing jobs.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_ms === null) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms.'ms';
        }

        if ($this->duration_ms < 60000) {
            return round($this->duration_ms / 1000, 2).'s';
        }

        $minutes = floor($this->duration_ms / 60000);
        $seconds = round(($this->duration_ms % 60000) / 1000, 2);

        return "{$minutes}m {$seconds}s";
    }

    /**
     * Get formatted memory usage.
     */
    public function getFormattedMemoryUsageAttribute(): string
    {
        if ($this->memory_start === null || $this->memory_end === null) {
            return 'N/A';
        }

        $used = $this->memory_end - $this->memory_start;
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $used > 0 ? floor(log($used, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return round($used / (1024 ** $power), 2).' '.$units[$power];
    }

    /**
     * Get short job class name.
     */
    public function getShortJobClassAttribute(): string
    {
        $parts = explode('\\', $this->job_class);

        return end($parts);
    }

    /**
     * Check if job is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    /**
     * Check if job is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
