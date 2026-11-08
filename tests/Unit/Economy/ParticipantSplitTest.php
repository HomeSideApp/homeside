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
     * Build a participant with an optional member, external identity and share count.
     *
     * @param  string|null  $memberId  The household member identifier, or null for an external share.
     * @param  SplitType  $splitType  The split strategy applied to the participant.
     * @param  int  $amountMinor  The explicit amount used by fixed splits.
     * @param  int|null  $percentage  The percentage used by percentage splits.
     * @param  string|null  $contactId  The optional address book contact link.
     * @param  string|null  $displayName  The optional external participant name.
     * @param  int  $shareCount  The number of heads represented by this participant.
     * @return EconomicTransactionParticipantData The participant data.
     */
    private function splitParticipant(
        ?string $memberId = null,
        SplitType $splitType = SplitType::Equal,
        int $amountMinor = 0,
        ?int $percentage = null,
        ?string $contactId = null,
        ?string $displayName = null,
        int $shareCount = 1,
    ): EconomicTransactionParticipantData {
        return new EconomicTransactionParticipantData(
            household_member_id: $memberId,
            split_type: $splitType,
            amount_minor: $amountMinor,
            percentage: $percentage,
            contact_id: $contactId,
            display_name: $displayName,
            share_count: $shareCount,
        );
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

    /**
     * Verify that an equal split treats share counts as additional heads.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_honours_share_count(): void
    {
        $result = $this->action->execute(1000, [
            $this->splitParticipant('m1', SplitType::Equal, 0, null, null, null, 1),
            $this->splitParticipant(null, SplitType::Equal, 0, null, null, null, 2),
        ]);

        // 3 heads: base 333 with a remainder of one cent for the first head.
        $this->assertSame([334, 666], array_column($result, 'amount_minor'));
        $this->assertSame(1000, array_sum(array_column($result, 'amount_minor')));
    }

    /**
     * Verify that single share counts keep the previous equal split behaviour.
     *
     * @return void This test method does not return a value.
     */
    public function test_equal_split_with_single_shares_keeps_previous_behaviour(): void
    {
        $result = $this->action->calculateEqualSplit(10001, [
            $this->splitParticipant('m1'),
            $this->splitParticipant('m2'),
            $this->splitParticipant('m3'),
        ]);

        // The remainder cent still lands on the first participants.
        $this->assertSame([3334, 3334, 3333], array_column($result, 'amount_minor'));

        $executed = $this->action->execute(10001, [
            $this->splitParticipant('m1'),
            $this->splitParticipant('m2'),
            $this->splitParticipant('m3'),
        ]);

        $this->assertSame([3334, 3334, 3333], array_column($executed, 'amount_minor'));
        $this->assertSame(10001, array_sum(array_column($executed, 'amount_minor')));
    }

    /**
     * Verify that a fixed split keeps explicit amounts for a named external participant.
     *
     * @return void This test method does not return a value.
     */
    public function test_fixed_split_with_named_external_participant(): void
    {
        $result = $this->action->execute(10000, [
            $this->splitParticipant('m1', SplitType::Fixed, 6000),
            $this->splitParticipant(null, SplitType::Fixed, 4000, null, 'c1', 'Ana'),
        ]);

        $this->assertSame([6000, 4000], array_column($result, 'amount_minor'));
        $this->assertSame(10000, array_sum(array_column($result, 'amount_minor')));

        $this->assertSame('m1', $result[0]['household_member_id']);
        $this->assertNull($result[1]['household_member_id']);
        $this->assertSame('c1', $result[1]['contact_id']);
        $this->assertSame('Ana', $result[1]['display_name']);
        $this->assertSame(1, $result[1]['share_count']);
        $this->assertSame(SplitType::Fixed, $result[1]['split_type']);
        $this->assertArrayHasKey('percentage', $result[1]);
    }

    /**
     * Verify that only one anonymous participant may take part in a split.
     *
     * @return void This test method does not return a value.
     */
    public function test_two_anonymous_participants_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('app.validation.participants_single_anonymous'));

        $this->action->execute(1000, [
            $this->splitParticipant('m1'),
            $this->splitParticipant(null, SplitType::Equal, 0, null, null, null, 1),
            $this->splitParticipant(null, SplitType::Equal, 0, null, null, null, 2),
        ]);
    }

    /**
     * Verify that external names are unique regardless of letter case.
     *
     * @return void This test method does not return a value.
     */
    public function test_two_external_participants_with_the_same_name_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('app.validation.participants_duplicate_identity'));

        $this->action->execute(1000, [
            $this->splitParticipant(null, SplitType::Equal, 0, null, null, 'Ana'),
            $this->splitParticipant(null, SplitType::Equal, 0, null, null, 'ana'),
        ]);
    }

    /**
     * Verify the identity key and anonymity mapping for each participant flavour.
     *
     * @return void This test method does not return a value.
     */
    public function test_identity_key_and_anonymity_mapping(): void
    {
        $member = $this->splitParticipant('m1', SplitType::Equal, 0, null, 'c1', 'Ignored name');
        $external = $this->splitParticipant(null, SplitType::Equal, 0, null, null, '  Ana  ');
        $anonymous = $this->splitParticipant(null, SplitType::Equal, 0, null, null, '   ');

        $this->assertSame('m1', $member->identityKey());
        $this->assertFalse($member->isAnonymous());

        $this->assertSame('ext:ana', $external->identityKey());
        $this->assertFalse($external->isAnonymous());

        $this->assertSame('anon', $anonymous->identityKey());
        $this->assertTrue($anonymous->isAnonymous());
    }
}
