<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobMonitoringFilterRequest;
use App\Models\JobRun;
use App\Services\JobMonitor;
use App\Services\JobMonitoringStatistics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for the job monitoring dashboard (Inertia pages).
 *
 * Authorization is enforced by the `permission:jobs-monitor.*` route middleware.
 */
class JobMonitoringController extends Controller
{
    public function __construct(
        protected JobMonitor $jobMonitor,
        protected JobMonitoringStatistics $statistics,
    ) {}

    /**
     * Display the job monitoring dashboard.
     *
     * Shows statistics cards, queue depth, recent jobs, and top failing jobs.
     */
    public function index(Request $request): Response
    {
        $period = $request->input('period', '24h');

        return Inertia::render('jobs-monitor/Dashboard', [
            'statistics' => fn (): array => $this->statistics->forPeriod($period),
            'queueDepths' => fn (): array => $this->jobMonitor->getQueueDepth(),
            'recentJobs' => fn () => $this->recentJobs($period),
            'topFailingJobs' => fn (): array => $this->statistics->topFailingJobs($period, 10),
            'filters' => ['period' => $period],
        ]);
    }

    /**
     * Display paginated list of jobs with filters.
     */
    public function jobs(JobMonitoringFilterRequest $request): Response
    {
        $filters = $request->toFilterData();

        $jobs = $this->statistics
            ->applyFilters(JobRun::query()->with(['user:id,name']), $filters)
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (JobRun $job) => $this->jobSummary($job, true));

        return Inertia::render('jobs-monitor/Jobs', [
            'jobs' => $jobs,
            'queues' => fn () => JobRun::distinct()->pluck('queue'),
            'tags' => fn (): array => $this->allTags(),
            'filters' => $filters,
        ]);
    }

    /**
     * Display detailed information about a specific job.
     */
    public function show(string $id): Response
    {
        $job = JobRun::with(['user:id,name', 'originalJobRun', 'retries'])->findOrFail($id);

        return Inertia::render('jobs-monitor/Show', [
            'job' => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'job_class' => $job->job_class,
                'short_job_class' => $job->short_job_class,
                'queue' => $job->queue,
                'connection' => $job->connection,
                'status' => $job->status,
                'duration' => $job->formatted_duration,
                'duration_ms' => $job->duration_ms,
                'memory_usage' => $job->formatted_memory_usage,
                'memory_start' => $job->memory_start,
                'memory_end' => $job->memory_end,
                'memory_peak' => $job->memory_peak,
                'cpu_user' => $job->cpu_user,
                'cpu_system' => $job->cpu_system,
                'user' => $job->user ? ['id' => $job->user->id, 'name' => $job->user->name] : null,
                'tags' => $job->tags,
                'payload' => $job->payload,
                'exception' => $job->exception,
                'stack_trace' => $job->stack_trace,
                'attempts' => $job->attempts,
                'started_at' => $job->started_at?->toDateTimeString(),
                'finished_at' => $job->finished_at?->toDateTimeString(),
                'created_at' => $job->created_at->toDateTimeString(),
                'original_job_run' => $job->originalJobRun ? [
                    'id' => $job->originalJobRun->id,
                    'status' => $job->originalJobRun->status,
                    'created_at' => $job->originalJobRun->created_at->toDateTimeString(),
                ] : null,
                'retries' => $job->retries->map(fn (JobRun $retry) => [
                    'id' => $retry->id,
                    'status' => $retry->status,
                    'duration_ms' => $retry->duration_ms,
                    'created_at' => $retry->created_at->toDateTimeString(),
                ]),
            ],
        ]);
    }

    /**
     * Retry a failed job.
     */
    public function retry(string $id): RedirectResponse
    {
        try {
            $this->jobMonitor->retryFailedJob($id);

            return redirect()->back()->with('success', 'Job queued for retry successfully');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to retry job: '.$e->getMessage());
        }
    }

    /**
     * Recent jobs for the dashboard period.
     *
     * @return LengthAwarePaginator
     */
    protected function recentJobs(string $period)
    {
        return JobRun::query()
            ->when($this->statistics->periodDate($period), fn ($q, $date) => $q->where('created_at', '>=', $date))
            ->with(['user:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (JobRun $job) => $this->jobSummary($job));
    }

    /**
     * Shape a job run for the listing payloads.
     *
     * @return array<string, mixed>
     */
    protected function jobSummary(JobRun $job, bool $withFullClass = false): array
    {
        $summary = [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'job_class' => $job->short_job_class,
            'queue' => $job->queue,
            'status' => $job->status,
            'duration' => $job->formatted_duration,
            'duration_ms' => $job->duration_ms,
            'user' => $job->user ? ['id' => $job->user->id, 'name' => $job->user->name] : null,
            'tags' => $job->tags,
            'created_at' => $job->created_at->toDateTimeString(),
            'created_at_human' => $job->created_at->diffForHumans(),
        ];

        if ($withFullClass) {
            $summary['full_job_class'] = $job->job_class;
        }

        return $summary;
    }

    /**
     * Extract unique tag names from the tags JSON field.
     *
     * @return array<int, string>
     */
    protected function allTags(): array
    {
        return JobRun::whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => collect($tags)->pluck('tag'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
