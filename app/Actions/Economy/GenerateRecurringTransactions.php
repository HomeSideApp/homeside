<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates due economic transaction occurrences from recurring source transactions.
 */
final class GenerateRecurringTransactions
{
    private const int MAX_OCCURRENCES_PER_SOURCE = 500;

    /**
     * Create a recurring transaction generation action instance.
     *
     * @param  CalculateNextRecurrence  $calculateNextRecurrence  The service that calculates each subsequent occurrence.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        private readonly CalculateNextRecurrence $calculateNextRecurrence,
    ) {}

    /**
     * Generate every due occurrence up to the supplied timestamp.
     *
     * Each source is locked while it advances, and the database uniqueness constraint prevents
     * duplicate occurrences if the command is retried.
     *
     * @param  CarbonInterface  $through  The inclusive timestamp through which occurrences should be generated.
     * @return int The number of newly generated transaction occurrences.
     */
    public function execute(CarbonInterface $through): int
    {
        $through = $through->copy()->endOfDay();
        $sourceIds = EconomicTransaction::query()
            ->whereNull('recurrence_parent_id')
            ->whereNotNull('recurrence_frequency')
            ->whereNotNull('recurrence_next_at')
            ->where('recurrence_next_at', '<=', $through)
            ->pluck('id');

        return $sourceIds->sum(
            fn (string $sourceId): int => $this->generateForSource($sourceId, $through),
        );
    }

    /**
     * Generate due occurrences for one locked recurrence source.
     *
     * @param  string  $sourceId  The recurring source transaction identifier.
     * @param  CarbonInterface  $through  The inclusive timestamp through which occurrences should be generated.
     * @return int The number of newly generated occurrences for the source.
     */
    private function generateForSource(string $sourceId, CarbonInterface $through): int
    {
        return DB::transaction(function () use ($sourceId, $through): int {
            $source = EconomicTransaction::query()
                ->whereKey($sourceId)
                ->lockForUpdate()
                ->first();

            if ($source === null) {
                return 0;
            }

            $anchor = $source->occurred_at;
            $frequency = $source->recurrence_frequency;

            if ($anchor === null
                || $frequency === null
                || $source->recurrence_next_at === null) {
                return 0;
            }

            $source->load(['items', 'taxes', 'participants']);
            $generatedCount = 0;
            $processedCount = 0;

            while ($source->recurrence_next_at !== null
                && $source->recurrence_next_at->lessThanOrEqualTo($through)
                && $processedCount < self::MAX_OCCURRENCES_PER_SOURCE) {
                $scheduledAt = $source->recurrence_next_at->copy();

                if ($source->recurrence_ends_at !== null
                    && $scheduledAt->isAfter($source->recurrence_ends_at->copy()->endOfDay())) {
                    $source->recurrence_next_at = null;
                    break;
                }

                $occurrence = EconomicTransaction::query()->firstOrCreate(
                    [
                        'recurrence_parent_id' => $source->id,
                        'occurred_at' => $scheduledAt,
                    ],
                    [
                        'household_id' => $source->household_id,
                        'created_by' => $source->created_by,
                        'type' => $source->type,
                        'scope' => $source->scope,
                        'title' => $source->title,
                        'amount_minor' => $source->amount_minor,
                        'currency' => $source->currency,
                        'place' => $source->place,
                        'source_document_id' => $source->source_document_id,
                        'notes' => $source->notes,
                    ],
                );

                if ($occurrence->wasRecentlyCreated) {
                    $this->copyBreakdown($source, $occurrence);
                    $generatedCount++;
                }

                $nextAt = Carbon::instance($this->calculateNextRecurrence->execute(
                    $anchor,
                    $scheduledAt,
                    $frequency,
                    $source->recurrence_interval ?? 1,
                    $source->recurrence_weekdays ?? [],
                ));
                $source->recurrence_next_at = $source->recurrence_ends_at !== null
                    && $nextAt->isAfter($source->recurrence_ends_at->copy()->endOfDay())
                        ? null
                        : $nextAt;
                $processedCount++;
            }

            $source->save();

            return $generatedCount;
        });
    }

    /**
     * Copy item, tax, and participant details from a source to a generated occurrence.
     *
     * @param  EconomicTransaction  $source  The recurrence source containing the template relations.
     * @param  EconomicTransaction  $occurrence  The newly generated transaction receiving independent relations.
     * @return void This method does not return a value.
     */
    private function copyBreakdown(
        EconomicTransaction $source,
        EconomicTransaction $occurrence,
    ): void {
        $occurrence->items()->createMany($source->items->map(fn ($item): array => [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_amount_minor' => $item->unit_amount_minor,
            'subtotal_minor' => $item->subtotal_minor,
            'tax_amount_minor' => $item->tax_amount_minor,
            'total_minor' => $item->total_minor,
            'position' => $item->position,
        ])->all());
        $occurrence->taxes()->createMany($source->taxes->map(fn ($tax): array => [
            'name' => $tax->name,
            'rate' => $tax->rate,
            'taxable_base_minor' => $tax->taxable_base_minor,
            'tax_amount_minor' => $tax->tax_amount_minor,
        ])->all());
        $occurrence->participants()->createMany($source->participants->map(fn ($participant): array => [
            'household_member_id' => $participant->household_member_id,
            'split_type' => $participant->split_type,
            'amount_minor' => $participant->amount_minor,
            'percentage' => $participant->percentage,
        ])->all());
    }
}
