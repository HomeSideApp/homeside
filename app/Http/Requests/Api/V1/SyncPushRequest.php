<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\Sync\PushSyncOperations;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the envelope of an offline push batch.
 *
 * Only the envelope is checked here. The verb and the path are kept loose on
 * purpose: an operation that names a route outside the offline write allowlist
 * has to answer `rejected` on its own, without stopping the rest of the batch.
 * The 50-operation cap is a whole-request rule, because an oversized outbox is a
 * client bug rather than one bad operation.
 */
final class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1', 'max:'.PushSyncOperations::MAX_OPERATIONS],
            'operations.*' => ['required', 'array'],
            'operations.*.op_id' => ['required', 'uuid'],
            'operations.*.idempotency_key' => ['required', 'uuid'],
            'operations.*.method' => ['required', 'string', 'max:16'],
            'operations.*.path' => ['required', 'string', 'max:2048'],
            'operations.*.body' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * The queued operations, in the order the device sent them.
     *
     * @return array<int, array{op_id: string, method: string, path: string, idempotency_key: string, body?: array<string, mixed>}> The validated operations.
     */
    public function operations(): array
    {
        /** @var array<int, array{op_id: string, method: string, path: string, idempotency_key: string, body?: array<string, mixed>}> $operations */
        $operations = $this->validated()['operations'];

        return array_values($operations);
    }
}
