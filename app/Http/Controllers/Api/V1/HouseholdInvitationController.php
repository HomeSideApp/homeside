<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Households\HouseholdInvitationResource;
use App\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class HouseholdInvitationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(InvitationStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $status = $validated['status'] ?? InvitationStatus::Pending->value;
        $query = HouseholdInvitation::query()
            ->where('email', $this->authenticatedUser($request)->email)
            ->with(['household', 'inviter'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($status === InvitationStatus::Pending->value) {
            $query->where('status', InvitationStatus::Pending)->where('expires_at', '>', now());
        } elseif ($status === InvitationStatus::Expired->value) {
            $query->where(function ($expired): void {
                $expired->where('status', InvitationStatus::Expired)
                    ->orWhere(function ($pending): void {
                        $pending->where('status', InvitationStatus::Pending)
                            ->where('expires_at', '<=', now());
                    });
            });
        } else {
            $query->where('status', $status);
        }

        return HouseholdInvitationResource::collection(
            $query->paginate($validated['perPage'] ?? 15)->withQueryString(),
        );
    }
}
