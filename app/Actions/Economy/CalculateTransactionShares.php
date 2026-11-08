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
     * @return array<int, array{household_member_id: string|null, contact_id: string|null, display_name: string|null, share_count: int, split_type: SplitType, amount_minor: int, percentage: int|null}> The normalized participant shares whose amounts equal the transaction total.
     */
    public function execute(int $totalMinor, array $participants): array
    {
        if ($participants === []) {
            return [];
        }

        $this->assertUniqueIdentities($participants);

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
                'contact_id' => $participant->contact_id,
                'display_name' => $participant->display_name,
                'share_count' => $participant->share_count,
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
     * Heads are expanded in participant order so each participant receives the sum of the
     * heads it owns; the first remainder heads each receive one extra minor unit.
     *
     * @param  int  $totalMinor  The transaction total expressed in minor currency units.
     * @param  EconomicTransactionParticipantData[]  $participants  The participants receiving equal shares.
     * @return array<int, array{household_member_id: string|null, amount_minor: int}> The calculated amount for each participant.
     */
    public function calculateEqualSplit(int $totalMinor, array $participants): array
    {
        if ($participants === []) {
            return [];
        }

        $heads = array_map(
            fn (EconomicTransactionParticipantData $participant): int => max(1, $participant->share_count),
            $participants,
        );

        $headCount = array_sum($heads);
        $baseAmount = intdiv($totalMinor, $headCount);
        $remainder = $totalMinor - ($baseAmount * $headCount);

        $result = [];
        $headIndex = 0;
        foreach ($participants as $index => $participant) {
            $amount = 0;

            for ($owned = 0; $owned < $heads[$index]; $owned++) {
                $amount += $baseAmount;
                if ($headIndex < $remainder) {
                    $amount += 1;
                }

                $headIndex++;
            }

            $result[] = [
                'household_member_id' => $participant->household_member_id,
                'amount_minor' => $amount,
            ];
        }

        return $result;
    }

    /**
     * Reject splits with more than one anonymous portion or with repeated identities.
     *
     * @param  EconomicTransactionParticipantData[]  $participants  The participants to inspect.
     * @return void This method does not return a value.
     *
     * @throws ValidationException When a split contains several anonymous portions or duplicated identities.
     */
    private function assertUniqueIdentities(array $participants): void
    {
        $anonymousCount = count(array_filter(
            $participants,
            fn (EconomicTransactionParticipantData $participant): bool => $participant->isAnonymous(),
        ));

        if ($anonymousCount > 1) {
            throw ValidationException::withMessages([
                'participants' => __('app.validation.participants_single_anonymous'),
            ]);
        }

        $identityKeys = array_map(
            fn (EconomicTransactionParticipantData $participant): string => $participant->identityKey(),
            $participants,
        );

        if (count(array_unique($identityKeys)) !== count($identityKeys)) {
            throw ValidationException::withMessages([
                'participants' => __('app.validation.participants_duplicate_identity'),
            ]);
        }
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
