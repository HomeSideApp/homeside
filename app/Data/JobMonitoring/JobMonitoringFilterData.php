<?php

declare(strict_types=1);

namespace App\Data\JobMonitoring;

/**
 * Validated filter set shared by the job monitoring dashboard and job listing.
 */
final readonly class JobMonitoringFilterData
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public ?string $period = null,
        public ?string $status = null,
        public ?string $queue = null,
        public ?string $jobClass = null,
        public array $tags = [],
        public string $tagMode = 'any',
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            period: isset($data['period']) ? (string) $data['period'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            queue: isset($data['queue']) ? (string) $data['queue'] : null,
            jobClass: isset($data['job_class']) ? (string) $data['job_class'] : null,
            tags: array_values(array_map('strval', $data['tags'] ?? [])),
            tagMode: (string) ($data['tag_mode'] ?? 'any'),
            dateFrom: isset($data['date_from']) ? (string) $data['date_from'] : null,
            dateTo: isset($data['date_to']) ? (string) $data['date_to'] : null,
        );
    }

    /**
     * Whether any list filter narrows the job listing query.
     */
    public function hasListFilters(): bool
    {
        return $this->status !== null
            || $this->queue !== null
            || $this->jobClass !== null
            || $this->tags !== []
            || $this->dateFrom !== null
            || $this->dateTo !== null;
    }
}
