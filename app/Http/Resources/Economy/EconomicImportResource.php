<?php

namespace App\Http\Resources\Economy;

use App\Models\EconomicImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicImport */
final class EconomicImportResource extends JsonResource
{
    /**
     * Transform the economic import into its API and Inertia representation.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized economic import data with resolved nested resources.
     */
    public function toArray(Request $request): array
    {
        if (! $request->is('api/*')) {
            return $this->webPayload($request);
        }

        $status = match ($this->status->value) {
            'pending' => 'queued',
            'ready_for_review' => 'review_required',
            default => $this->status->value,
        };
        $progress = match ($status) {
            'queued' => 0,
            'processing' => 50,
            default => 100,
        };

        $rawDraft = $this->review_payload ?? $this->extracted_payload;
        $draft = is_array($rawDraft) ? $this->normalizeDraft($rawDraft) : null;

        return [
            'id' => $this->id,
            'status' => $status,
            'progress' => $progress,
            'document_id' => $this->document_id,
            'draft' => $draft,
            'warnings' => is_array($rawDraft) ? ($rawDraft['warnings'] ?? []) : [],
            'poll_after_ms' => in_array($status, ['queued', 'processing'], true) ? 1500 : null,
            'scope' => [
                'household_id' => $this->household_id,
            ],
            'document' => $this->whenLoaded(
                'document',
                fn (): ?array => $this->document === null
                    ? null
                    : (new EconomicDocumentResource($this->document))->resolve($request),
            ),
            'ai_run' => $this->whenLoaded('aiRun', fn () => $this->aiRun !== null ? [
                'id' => $this->aiRun->id,
                'provider_name' => $this->aiRun->provider_name,
                'model_name' => $this->aiRun->model_name,
                'duration_ms' => $this->aiRun->duration_ms,
            ] : null),
            'error' => $this->error_code ? [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ] : null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function webPayload(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'scope' => ['household_id' => $this->household_id],
            'document' => $this->whenLoaded(
                'document',
                fn (): ?array => $this->document === null
                    ? null
                    : (new EconomicDocumentResource($this->document))->resolve($request),
            ),
            'ai_run' => $this->whenLoaded('aiRun', fn () => $this->aiRun !== null ? [
                'id' => $this->aiRun->id,
                'provider_name' => $this->aiRun->provider_name,
                'model_name' => $this->aiRun->model_name,
                'duration_ms' => $this->aiRun->duration_ms,
            ] : null),
            'requested_sections' => $this->requested_sections,
            'extracted' => $this->extracted_payload,
            'review_payload' => $this->review_payload,
            'error' => $this->error_code ? [
                'code' => $this->error_code,
                'message' => $this->error_message,
            ] : null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function normalizeDraft(array $draft): array
    {
        if (array_key_exists('amount', $draft) && ! array_key_exists('amount_minor', $draft)) {
            $draft['amount_minor'] = (int) round((float) $draft['amount'] * 100);
        }
        unset($draft['amount'], $draft['warnings']);

        $draft['items'] = array_map(function (array $item): array {
            foreach (['unit_amount', 'subtotal', 'tax_amount', 'total'] as $field) {
                $minorField = $field.'_minor';
                if (array_key_exists($field, $item) && ! array_key_exists($minorField, $item)) {
                    $item[$minorField] = (int) round((float) $item[$field] * 100);
                }
                unset($item[$field]);
            }

            return $item;
        }, is_array($draft['items'] ?? null) ? $draft['items'] : []);

        $draft['taxes'] = array_map(function (array $tax): array {
            foreach (['taxable_base', 'amount'] as $field) {
                $minorField = $field === 'amount' ? 'amount_minor' : $field.'_minor';
                if (array_key_exists($field, $tax) && ! array_key_exists($minorField, $tax)) {
                    $tax[$minorField] = (int) round((float) $tax[$field] * 100);
                }
                unset($tax[$field]);
            }

            return $tax;
        }, is_array($draft['taxes'] ?? null) ? $draft['taxes'] : []);

        $draft['participants'] = array_map(function (array $participant): array {
            if (array_key_exists('amount', $participant) && ! array_key_exists('amount_minor', $participant)) {
                $participant['amount_minor'] = (int) round((float) $participant['amount'] * 100);
            }
            unset($participant['amount']);

            return $participant;
        }, is_array($draft['participants'] ?? null) ? $draft['participants'] : []);

        return $draft;
    }
}
