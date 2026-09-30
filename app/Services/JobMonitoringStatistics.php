<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\JobMonitoring\JobMonitoringFilterData;
use App\Models\JobRun;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated job run statistics shared by the web dashboard and the API.
 */
class JobMonitoringStatistics
{
    /**
     * Calculate statistics for a given period.
     *
     * @return array{total: int, processed: int, failed: int, processing: int, success_rate: float}
     */
    public function forPeriod(?string $period = '24h'): array
    {
        $query = JobRun::query()
            ->when($this->periodDate($period), fn ($q, $date) => $q->where('created_at', '>=', $date));

        $total = $query->count();
        $processed = (clone $query)->where('status', 'processed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $processing = (clone $query)->where('status', 'processing')->count();
        $successRate = $total > 0 ? round(($processed / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'processing' => $processing,
            'success_rate' => $successRate,
        ];
    }

    /**
     * Get top failing job classes for a period.
     *
     * @return array<int, array{job_class: string, full_class: string, failures: int}>
     */
    public function topFailingJobs(?string $period = '24h', int $limit = 10): array
    {
        return JobRun::failed()
            ->when($this->periodDate($period), fn ($q, $date) => $q->where('created_at', '>=', $date))
            ->select('job_class', DB::raw('count(*) as failures'))
            ->groupBy('job_class')
            ->orderByDesc('failures')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'job_class' => class_basename($item->job_class),
                'full_class' => $item->job_class,
                'failures' => (int) $item->failures,
            ])
            ->toArray();
    }

    /**
     * Apply the validated list filters to a job run query.
     */
    public function applyFilters(Builder $query, JobMonitoringFilterData $filters): Builder
    {
        if ($filters->status !== null) {
            $query->byStatus($filters->status);
        }

        if ($filters->queue !== null) {
            $query->byQueue($filters->queue);
        }

        if ($filters->jobClass !== null) {
            $query->byJobClass($filters->jobClass);
        }

        if ($filters->tags !== []) {
            $query->withTags($filters->tags, $filters->tagMode);
        }

        if ($filters->dateFrom !== null) {
            $query->where('created_at', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->where('created_at', '<=', $filters->dateTo);
        }

        return $query;
    }

    /**
     * Get date from period string.
     */
    public function periodDate(?string $period): ?CarbonInterface
    {
        return match ($period) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => null,
        };
    }
}
