<?php

namespace App\Http\Resources\Economy;

use App\Models\EconomicTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicTransaction */
final class EconomicTransactionResource extends JsonResource
{
    /**
     * Transform an economic transaction into its public API representation.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized transaction and any loaded relations.
     */
    public function toArray(Request $request): array
    {
        // Child resources format their amounts with the precision of the funding account, so the
        // parent is attached to them before they are serialized.
        foreach (['items', 'taxes', 'participants'] as $relation) {
            if (! $this->resource->relationLoaded($relation)) {
                continue;
            }

            foreach ($this->resource->getRelation($relation) as $child) {
                $child->setRelation('transaction', $this->resource);
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'scope' => $this->scope->value,
            'title' => $this->title,
            'amount' => $this->displayAmount(),
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'place' => $this->place,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'notes' => $this->notes,
            'recurrence_parent_id' => $this->recurrence_parent_id,
            'recurrence_frequency' => $this->recurrence_frequency?->value,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_weekdays' => $this->recurrence_weekdays ?? [],
            'recurrence_ends_at' => $this->recurrence_ends_at?->toDateString(),
            'recurrence_next_at' => $this->recurrence_next_at?->toISOString(),
            'household_id' => $this->household_id,
            'account' => $this->resolveVisibleAccount($request),
            'contact' => $this->resolveVisibleContact($request),
            'origin' => $this->household_id === null
                ? null
                : $this->whenLoaded('household', fn (): ?string => $this->household?->name),
            'created_by' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ],
            'items' => $this->whenLoaded(
                'items',
                fn (): array => EconomicTransactionItemResource::collection($this->items)->resolve($request),
            ),
            'taxes' => $this->whenLoaded(
                'taxes',
                fn (): array => EconomicTransactionTaxResource::collection($this->taxes)->resolve($request),
            ),
            'participants' => $this->whenLoaded(
                'participants',
                fn (): array => EconomicTransactionParticipantResource::collection($this->participants)->resolve($request),
            ),
            'attachments' => $this->whenLoaded(
                'attachments',
                fn (): array => EconomicTransactionAttachmentResource::collection($this->attachments)->resolve($request),
            ),
            'source_document' => $this->whenLoaded(
                'sourceDocument',
                fn (): ?array => $this->sourceDocument === null
                    ? null
                    : (new EconomicDocumentResource($this->sourceDocument))->resolve($request),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Format the transaction amount for display.
     *
     * The precision follows the linked account, because a crypto account stores satoshis and
     * formatting it with two decimals would silently lose the real value.
     *
     * @return string The amount formatted for display.
     */
    private function displayAmount(): string
    {
        $decimalPlaces = $this->relationLoaded('account') && $this->account !== null
            ? $this->account->decimal_places
            : 2;

        return number_format(
            $this->amount_minor / (10 ** $decimalPlaces),
            $decimalPlaces,
            '.',
            '',
        );
    }

    /**
     * Determine whether the private account association may be exposed to the viewer.
     *
     * The account that funded a movement is personal bookkeeping: even inside a household, only
     * the transaction creator is allowed to know which of their own accounts paid for it.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return bool True when the viewer created the transaction; otherwise false.
     */
    private function isVisibleToViewer(Request $request): bool
    {
        return $request->user()?->id === $this->created_by;
    }

    /**
     * Resolve the private account association for the current viewer.
     *
     * The key is always present in the payload so API clients can rely on a stable contract,
     * but it is null for every viewer other than the transaction creator.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>|null The serialized account for its owner, or null otherwise.
     */
    private function resolveVisibleAccount(Request $request): ?array
    {
        if (! $this->isVisibleToViewer($request) || ! $this->relationLoaded('account')) {
            return null;
        }

        return $this->account === null
            ? null
            : (new EconomicAccountResource($this->account))->resolve($request);
    }

    /**
     * Resolve the optional contact linked to the transaction for the current viewer.
     *
     * The linked contact is private bookkeeping of the transaction creator, so only they see it.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>|null The serialized contact summary for its owner, or null otherwise.
     */
    private function resolveVisibleContact(Request $request): ?array
    {
        if (! $this->isVisibleToViewer($request) || ! $this->relationLoaded('contacts')) {
            return null;
        }

        $contact = $this->contacts->firstWhere('pivot.role', 'related');

        return $contact === null
            ? null
            : [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'avatar_url' => $contact->preferred_photo_record_id
                    ? route('contacts.avatar', $contact->id)
                    : null,
            ];
    }
}
