<?php

namespace App\Data\Economy;

/**
 * Class ImportFiltersData
 *
 * Immutable filters used when listing economic imports, covering the active, review, failure and
 * historical views of the import lifecycle.
 */
final readonly class ImportFiltersData
{
    /**
     * Create immutable import listing filters.
     *
     * @param  string  $status  The requested view: all, active, ready, failed or history.
     * @param  string|null  $created_by  The optional household member identifier filter.
     * @param  string|null  $search  The optional document filename search term.
     * @param  int  $per_page  The number of imports per page.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $status = 'active',
        public ?string $created_by = null,
        public ?string $search = null,
        public int $per_page = 15,
    ) {}

    /**
     * Build import filters from a validated request payload.
     *
     * @param  array{status?: string|null, created_by?: string|null, search?: string|null, perPage?: int|string|null}  $data  The validated filter values keyed by request field.
     * @return self The normalized immutable import filters.
     */
    public static function fromArray(array $data): self
    {
        $perPage = (int) ($data['perPage'] ?? 15);

        return new self(
            status: $data['status'] ?? 'active',
            created_by: $data['created_by'] ?? null,
            search: $data['search'] ?? null,
            per_page: min(max($perPage, 1), 100),
        );
    }

    /**
     * Resolve the import statuses covered by the selected filter.
     *
     * @return array<int, string> The status values matched by the active filter.
     */
    public function statuses(): array
    {
        return match ($this->status) {
            'active' => ['pending', 'processing', 'ready_for_review', 'failed'],
            'ready' => ['ready_for_review'],
            'failed' => ['failed'],
            'history' => ['confirmed', 'discarded'],
            default => [],
        };
    }
}
