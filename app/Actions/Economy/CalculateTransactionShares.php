<?php

namespace App\Actions\Economy;

use App\Data\Economy\EconomicTransactionParticipantData;
use App\Enums\SplitType;
use Illuminate\Validation\ValidationException;

final class CalculateTransactionShares
{
    /**
     * Calculate and normalize the monetary share assigned to each participant.
     *
     * @param  int  $totalMinor  The transaction total expressed in minor currency units.
     * @param  EconomicTransactionParticipantData[]  $participants  The participant definitions to normalize.
     * @return array<int, array{household_member_id: string, split_type: SplitType, amount_minor: int, percentage: int|null}> The normalized participant shares whose amounts equal the transaction total.
     */
    public function execute(int $totalMinor, array $participants): array
    {
        if ($participants === []) {
            return [];
        }

        $splitTypes = array_values(array_unique(array_map(
            fn (EconomicTransactionParticipantData $participant): string => $participant->split_type->value,
            $participants,
        )));

        if (count($splitTypes) !== 1) {
            throw ValidationException::withMessages([
                'participants' => __('app.validation.participants_same_split_type'),
            ]);
        }

        $amounts = match ($participants[0]->split_type) {
            SplitType::Equal => array_column($this->calculateEqualSplit($totalMinor, $participants), 'amount_minor'),
            SplitType::Fixed => array_map(
                fn (EconomicTransactionParticipantData $participant): int => $participant->amount_minor,
                $participants,
            ),
            SplitType::Percentage => $this->calculatePercentageSplit($totalMinor, $participants),
        };

        if (array_sum($amounts) !== $totalMinor) {
            throw ValidationException::withMessages([
                'participants' => __('app.validation.participant_split_total'),
            ]);
        }

        return array_map(
            fn (EconomicTransactionParticipantData $participant, int $amount): array => [
                'household_member_id' => $participant->household_member_id,
                'split_type' => $participant->split_type,
                'amount_minor' => $amount,
                'percentage' => $participant->percentage,
            ],
            $participants,
            $amounts,
        );
    }

    /**
     * Calculate equal participant shares with deterministic remainder distribution.
     *
     * @param  int  $totalMinor  The transaction total expressed in minor currency units.
     * @param  EconomicTransactionParticipantData[]  $participants  The participants receiving equal shares.
     * @return array<int, array{household_member_id: string, amount_minor: int}> The calculated amount for each participant.
     */
    public function calculateEqualSplit(int $totalMinor, array $participants): array
    {
        $count = count($participants);
        if ($count === 0) {
            return [];
        }

        $baseAmount = intdiv($totalMinor, $count);
        $remainder = $totalMinor - ($baseAmount * $count);

        $result = [];
        foreach ($participants as $index => $participant) {
            $amount = $baseAmount;
            if ($index < $remainder) {
                $amount += 1;
            }

            $result[] = [
                'household_member_id' => $participant->household_member_id,
                'amount_minor' => $amount,
            ];
        }

        return $result;
    }

    /**
     * Determine whether the participant amounts exactly match a transaction total.
     *
     * @param  int  $totalMinor  The expected total expressed in minor currency units.
     * @param  EconomicTransactionParticipantData[]  $participants  The participant shares to validate.
     * @return bool True when the participant amounts match the expected total; otherwise false.
     */
    public function validateSplits(int $totalMinor, array $participants): bool
    {
        $sum = array_sum(array_map(
            fn (EconomicTransactionParticipantData $participant): int => $participant->amount_minor,
            $participants,
        ));

        return $sum === $totalMinor;
    }

    /**
     * Calculate percentage-based shares and distribute any rounding remainder.
     *
     * @param  int  $totalMinor  The transaction total expressed in minor currency units.
     * @param  EconomicTransactionParticipantData[]  $participants  The participants and their percentages.
     * @return int[] The calculated amount in minor units for each participant, in input order.
     */
    private function calculatePercentageSplit(int $totalMinor, array $participants): array
    {
        $percentageTotal = array_sum(array_map(
            fn (EconomicTransactionParticipantData $participant): int => $participant->percentage ?? 0,
            $participants,
        ));

        if ($percentageTotal !== 100) {
            throw ValidationException::withMessages([
                'participants' => __('app.validation.participant_percentage_total'),
            ]);
        }

        $amounts = array_map(
            fn (EconomicTransactionParticipantData $participant): int => intdiv(
                $totalMinor * ($participant->percentage ?? 0),
                100,
            ),
            $participants,
        );

        $remainder = $totalMinor - array_sum($amounts);

        for ($index = 0; $index < $remainder; $index++) {
            $amounts[$index % count($amounts)]++;
        }

        return $amounts;
    }
}
