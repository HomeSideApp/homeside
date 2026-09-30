<?php

namespace App\Http\Resources\Households;

use App\Models\HouseholdMember;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdMember */
final class HouseholdMemberResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $this->resource->loadMissing('user');

        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'joined_at' => $this->joined_at?->toISOString(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'contact' => $this->resolvedContact($request),
        ];
    }

    /**
     * Resolve the viewer's contact matching the member's user email.
     *
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>|null The contact summary, or null when unmatched.
     */
    private function resolvedContact($request): ?array
    {
        $viewer = $request->user();

        if ($viewer === null) {
            return null;
        }

        $service = app(ContactResolutionService::class);

        return $service->summary($service->forHouseholdMember($this->resource, $viewer));
    }
}
