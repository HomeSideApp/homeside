<?php

namespace Tests\Unit\Economy;

use App\Actions\Economy\CalculateTransactionShares;
use App\Data\Economy\EconomicTransactionParticipantData;
use App\Enums\SplitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ParticipantSplitTest extends TestCase
{
    use RefreshDatabase;

    private CalculateTransactionShares $action;

    /**
     * Create the share calculation action used by each test.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CalculateTransactionShares;
    }

    /**
     * Build an equal-share participant for a household member.
     *
     * @param  string  $memberId  The household member identifier assigned to the participant.
     * @return EconomicTransactionParticipantData The participant data configured for an equal split.
     */
    private function participant(string $memberId): EconomicTransactionParticipantData
    {
        return new EconomicTransactionParticipantData($memberId, SplitType::Equal, 0, null);
    }

    /**
     * Verify that two participants divide an even total equally.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_two_people(): void
    {
        $result = $this->action->calculateEqualSplit(10000, [
            $this->participant('m1'),
            $this->participant('m2'),
        ]);

        $this->assertSame([5000, 5000], array_column($result, 'amount_minor'));
    }

    /**
     * Verify that three participants divide a compatible total equally.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_three_people(): void
    {
        $result = $this->action->calculateEqualSplit(9999, [
            $this->participant('m1'),
            $this->participant('m2'),
            $this->participant('m3'),
        ]);

        $this->assertSame([3333, 3333, 3333], array_column($result, 'amount_minor'));
    }

    /**
     * Verify that an uneven equal split distributes its remainder deterministically.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_odd_amount_distributes_remainder(): void
    {
        $result = $this->action->calculateEqualSplit(10001, [
            $this->participant('m1'),
            $this->participant('m2'),
            $this->participant('m3'),
        ]);

        // 10001 / 3 leaves a remainder of two assigned to the first participants.
        $this->assertSame([3334, 3334, 3333], array_column($result, 'amount_minor'));

        // The calculated shares must always equal the original total.
        $this->assertSame(10001, array_sum(array_column($result, 'amount_minor')));
    }

    /**
     * Verify that one hundred euros split three ways retains the original total.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_100_eur_3_people(): void
    {
        $result = $this->action->calculateEqualSplit(10000, [
            $this->participant('m1'),
            $this->participant('m2'),
            $this->participant('m3'),
        ]);

        $this->assertSame(10000, array_sum(array_column($result, 'amount_minor')));
    }

    /**
     * Verify that calculated equal shares sum to the transaction total.
     *
     * @return void This test method does not return a value.
     */
    public function test_split_sums_to_transaction_total(): void
    {
        $result = $this->action->calculateEqualSplit(12345, [
            $this->participant('m1'),
            $this->participant('m2'),
            $this->participant('m3'),
            $this->participant('m4'),
        ]);

        $this->assertSame(12345, array_sum(array_column($result, 'amount_minor')));
    }

    /**
     * Verify that an empty participant list produces no calculated shares.
     *
     * @return void This test method does not return a value.
     */
    public function test_empty_participants_returns_empty(): void
    {
        $this->assertSame([], $this->action->calculateEqualSplit(1000, []));
    }

    /**
     * Verify explicit participant amounts against matching and mismatched totals.
     *
     * @return void This test method does not return a value.
     */
    public function test_validate_splits(): void
    {
        $p1 = new EconomicTransactionParticipantData('m1', SplitType::Fixed, 5000, null);
        $p2 = new EconomicTransactionParticipantData('m2', SplitType::Fixed, 5000, null);

        $this->assertTrue($this->action->validateSplits(10000, [$p1, $p2]));
        $this->assertFalse($this->action->validateSplits(9999, [$p1, $p2]));
    }

    /**
     * Verify that percentage shares distribute rounding remainders deterministically.
     *
     * @return void This test method does not return a value.
     */
    public function test_percentage_split_distributes_rounding_remainder(): void
    {
        $participants = [
            new EconomicTransactionParticipantData('m1', SplitType::Percentage, 0, 50),
            new EconomicTransactionParticipantData('m2', SplitType::Percentage, 0, 50),
        ];

        $result = $this->action->execute(1001, $participants);

        $this->assertSame([501, 500], array_column($result, 'amount_minor'));
        $this->assertSame(1001, array_sum(array_column($result, 'amount_minor')));
    }

    /**
     * Verify that percentage-based shares must add up to one hundred percent.
     *
     * @return void This test method does not return a value.
     */
    public function test_percentage_split_must_sum_one_hundred(): void
    {
        $this->expectException(ValidationException::class);

        $this->action->execute(1000, [
            new EconomicTransactionParticipantData('m1', SplitType::Percentage, 0, 40),
            new EconomicTransactionParticipantData('m2', SplitType::Percentage, 0, 40),
        ]);
    }
}
