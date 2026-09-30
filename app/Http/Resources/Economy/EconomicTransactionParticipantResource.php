<?php

namespace App\Http\Resources\Economy;

use App\Http\Resources\Economy\Concerns\FormatsMinorAmounts;
use App\Models\EconomicTransactionParticipant;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicTransactionParticipant */
final class EconomicTransactionParticipantResource extends JsonResource
{
    use FormatsMinorAmounts;

    /**
     * Transform a shared transaction participant into its public representation.
     *
     * `amount` is the field the web detail badges render, while `amount_minor` keeps the integer
     * value consumed by the mobile contract.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized participant.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'split_type' => $this->split_type->value,
            'amount' => $this->formatMinor($this->amount_minor),
            'percentage' => $this->percentage,
            'amount_minor' => $this->amount_minor,
            'household_member' => [
                'id' => $this->householdMember?->id,
                'user' => [
                    'id' => $this->householdMember?->user?->id,
                    'name' => $this->householdMember?->user?->name,
                ],
                'contact' => $this->resolvedContact($request),
            ],
        ];
    }

    /**
     * Resolve the viewer's contact matching the participant's member email.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>|null The contact summary, or null when unmatched.
     */
    private function resolvedContact(Request $request): ?array
    {
        $viewer = $request->user();
        $member = $this->resource->householdMember;

        if ($viewer === null || $member === null) {
            return null;
        }

        $member->loadMissing('user');
        $service = app(ContactResolutionService::class);

        return $service->summary($service->forHouseholdMember($member, $viewer));
    }
}
