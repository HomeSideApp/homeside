<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobMonitoringFilterRequest;
use App\Http\Resources\JobMonitoring\JobRunResource;
use App\Models\JobRun;
use App\Services\JobMonitor;
use App\Services\JobMonitoringStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * API for the job monitoring system (job runs, statistics and queue depth).
 */
final class JobMonitoringController extends Controller
{
    public function __construct(
        protected JobMonitor $jobMonitor,
        protected JobMonitoringStatistics $statistics,
    ) {}

    /**
     * List job runs with optional filters.
     */
    public function index(JobMonitoringFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $request->toFilterData();

        $jobs = $this->statistics
            ->applyFilters(JobRun::query()->with(['user:id,name', 'retries']), $filters)
            ->latest()
            ->paginate($request->integer('perPage', 15))
            ->withQueryString();

        return JobRunResource::collection($jobs);
    }

    /**
     * Show a single job run.
     */
    public function show(string $id): JobRunResource
    {
        $job = JobRun::with(['user:id,name', 'originalJobRun', 'retries'])->findOrFail($id);

        return JobRunResource::make($job);
    }

    /**
     * Real-time statistics for a period.
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->input('period', '24h');

        return response()->json($this->statistics->forPeriod($period));
    }

    /**
     * Pending job depth per queue.
     */
    public function queueDepth(): JsonResponse
    {
        return response()->json($this->jobMonitor->getQueueDepth());
    }

    /**
     * Latest failed job runs.
     */
    public function failedJobs(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 100);

        $failedJobs = JobRun::failed()
            ->with('user:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (JobRun $job) => [
                'id' => $job->id,
                'job_class' => $job->short_job_class,
                'exception' => $job->exception,
                'queue' => $job->queue,
                'user' => $job->user?->name,
                'created_at' => $job->created_at->diffForHumans(),
            ]);

        return response()->json($failedJobs);
    }

    /**
     * Job runs filtered by a tag.
     */
    public function jobsByTag(string $tag, Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 100);

        $jobs = JobRun::withTags([$tag])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (JobRun $job) => [
                'id' => $job->id,
                'job_class' => $job->short_job_class,
                'status' => $job->status,
                'queue' => $job->queue,
                'created_at' => $job->created_at->diffForHumans(),
            ]);

        return response()->json($jobs);
    }

    /**
     * Retry a failed job run.
     */
    public function retry(string $id): JsonResponse
    {
        try {
            $this->jobMonitor->retryFailedJob($id);

            return response()->json([
                'message' => 'Job queued for retry successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to retry job: '.$e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
