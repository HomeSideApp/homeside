<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\JobMonitoring\JobMonitoringFilterData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobMonitoringFilterRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route's `permission:` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?: null,
            'queue' => $this->queue ?: null,
            'job_class' => $this->job_class ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['1h', '6h', '24h', '7d', '30d'])],
            'status' => ['nullable', 'string', Rule::in(['processing', 'processed', 'failed'])],
            'queue' => ['nullable', 'string', 'max:255'],
            'job_class' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'tag_mode' => ['nullable', 'string', Rule::in(['any', 'all'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The status must be one of: processing, processed, failed.',
            'tag_mode.in' => 'The tag mode must be either "any" or "all".',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }

    /**
     * Get the validated filters as a DTO.
     */
    public function toFilterData(): JobMonitoringFilterData
    {
        return JobMonitoringFilterData::fromArray($this->validated());
    }
}
