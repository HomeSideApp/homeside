<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\Sync\GetSyncChanges;
use App\Actions\Sync\SyncCursor;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the offline delta feed query.
 *
 * The cursor is opaque, so the only requirement is that it decodes into the
 * keyset the endpoint emitted. A malformed value is a validation error, never a
 * server error.
 */
final class SyncChangesRequest extends FormRequest
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
            'since' => [
                'sometimes',
                'string',
                'max:512',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || SyncCursor::decode($value) === null) {
                        $fail('The given sync cursor is not valid.');
                    }
                },
            ],
            'limit' => ['sometimes', 'integer', 'between:1,'.GetSyncChanges::MAX_LIMIT],
        ];
    }

    /**
     * The cursor to continue from, or null for the full initial state.
     */
    public function since(): ?string
    {
        $value = $this->validated('since');

        return is_string($value) ? $value : null;
    }

    /**
     * The maximum number of rows to emit.
     */
    public function limit(): int
    {
        $value = $this->validated('limit');

        return is_numeric($value) ? (int) $value : GetSyncChanges::DEFAULT_LIMIT;
    }
}
