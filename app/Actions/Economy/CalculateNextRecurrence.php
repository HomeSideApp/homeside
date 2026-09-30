<?php

namespace App\Actions\Economy;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonInterface;

/**
 * Calculates recurrence dates while preserving monthly and yearly anchor days.
 */
final class CalculateNextRecurrence
{
    /**
     * Calculate the occurrence immediately following the current scheduled date.
     *
     * @param  CarbonInterface  $anchor  The first occurrence whose calendar day anchors the series.
     * @param  CarbonInterface  $current  The occurrence from which the next date is calculated.
     * @param  RecurrenceFrequency  $frequency  The recurrence frequency to apply.
     * @param  int  $interval  The positive number of frequency units between occurrences.
     * @param  array<int, int>  $weekdays  The selected ISO weekdays for a weekly schedule, where Monday is 1 and Sunday is 7.
     * @return CarbonInterface The next scheduled occurrence date and time.
     */
    public function execute(
        CarbonInterface $anchor,
        CarbonInterface $current,
        RecurrenceFrequency $frequency,
        int $interval,
        array $weekdays = [],
    ): CarbonInterface {
        $normalizedInterval = max(1, $interval);

        return match ($frequency) {
            RecurrenceFrequency::Daily => $current->copy()->addDays($normalizedInterval),
            RecurrenceFrequency::Weekly => $this->nextWeeklyDate(
                $anchor,
                $current,
                $normalizedInterval,
                $weekdays,
            ),
            RecurrenceFrequency::Monthly => $this->nextMonthlyDate($anchor, $current, $normalizedInterval),
            RecurrenceFrequency::Yearly => $this->nextYearlyDate($anchor, $current, $normalizedInterval),
        };
    }

    /**
     * Calculate the next selected weekday within the active weekly interval.
     *
     * A weekly interval is anchored to the ISO week containing the first occurrence. For example,
     * an interval of two enables the selected days in one week and skips the following week.
     *
     * @param  CarbonInterface  $anchor  The first occurrence whose ISO week anchors the schedule.
     * @param  CarbonInterface  $current  The occurrence from which the next date is calculated.
     * @param  int  $interval  The positive number of weeks between active schedule weeks.
     * @param  array<int, int>  $weekdays  The selected ISO weekdays, where Monday is 1 and Sunday is 7.
     * @return CarbonInterface The next selected weekday with the current occurrence time preserved.
     */
    private function nextWeeklyDate(
        CarbonInterface $anchor,
        CarbonInterface $current,
        int $interval,
        array $weekdays,
    ): CarbonInterface {
        $normalizedWeekdays = array_values(array_unique(array_filter(
            array_map(static fn (mixed $weekday): int => (int) $weekday, $weekdays),
            static fn (int $weekday): bool => $weekday >= 1 && $weekday <= 7,
        )));
        sort($normalizedWeekdays);

        if ($normalizedWeekdays === []) {
            return $current->copy()->addWeeks($interval);
        }

        $anchorWeek = $anchor->copy()
            ->startOfWeek(CarbonInterface::MONDAY)
            ->startOfDay();
        $maximumDaysToInspect = ($interval * 7) + 7;

        for ($offset = 1; $offset <= $maximumDaysToInspect; $offset++) {
            $candidate = $current->copy()->addDays($offset);
            $candidateWeek = $candidate->copy()
                ->startOfWeek(CarbonInterface::MONDAY)
                ->startOfDay();
            $weeksSinceAnchor = (int) round(
                $anchorWeek->diffInDays($candidateWeek, false) / 7,
            );

            if ($weeksSinceAnchor >= 0
                && $weeksSinceAnchor % $interval === 0
                && in_array($candidate->dayOfWeekIso, $normalizedWeekdays, true)) {
                return $candidate;
            }
        }

        return $current->copy()->addWeeks($interval);
    }

    /**
     * Calculate a monthly date without permanently drifting from the anchor day.
     *
     * @param  CarbonInterface  $anchor  The first occurrence that supplies the preferred day of month.
     * @param  CarbonInterface  $current  The current scheduled occurrence.
     * @param  int  $interval  The positive number of months to advance.
     * @return CarbonInterface The next monthly occurrence, clamped to the target month's final day.
     */
    private function nextMonthlyDate(
        CarbonInterface $anchor,
        CarbonInterface $current,
        int $interval,
    ): CarbonInterface {
        $target = $current->copy()->startOfMonth()->addMonths($interval);
        $day = min($anchor->day, $target->daysInMonth);

        return $target->setDate($target->year, $target->month, $day)
            ->setTimeFrom($current);
    }

    /**
     * Calculate a yearly date while preserving leap-day series when possible.
     *
     * @param  CarbonInterface  $anchor  The first occurrence that supplies the preferred month and day.
     * @param  CarbonInterface  $current  The current scheduled occurrence.
     * @param  int  $interval  The positive number of years to advance.
     * @return CarbonInterface The next yearly occurrence, clamped to the target month's final day.
     */
    private function nextYearlyDate(
        CarbonInterface $anchor,
        CarbonInterface $current,
        int $interval,
    ): CarbonInterface {
        $target = $current->copy()->setDate($current->year + $interval, $anchor->month, 1);
        $day = min($anchor->day, $target->daysInMonth);

        return $target->setDate($target->year, $target->month, $day)
            ->setTimeFrom($current);
    }
}
