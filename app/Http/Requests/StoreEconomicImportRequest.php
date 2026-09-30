<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class StoreEconomicImportRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create an import in the requested scope.
     *
     * @return bool True when the user is authenticated and belongs to the routed household, or when the route is private.
     */
    public function authorize(): bool
    {
        $household = $this->resolveHousehold();

        if ($household === null) {
            return $this->user() !== null;
        }

        return $this->user()?->isMemberOf($household) ?? false;
    }

    /**
     * Define validation rules for creating an economic document import.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        return [
            'document_id' => ['required', 'uuid', 'exists:economic_documents,id'],
            'sections.place' => ['sometimes', 'boolean'],
            'sections.date' => ['sometimes', 'boolean'],
            'sections.items' => ['sometimes', 'boolean'],
            'sections.taxes' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Resolve the optional household route parameter before request authorization completes.
     *
     * @return Household|null The routed household, or null for a private economy route.
     */
    private function resolveHousehold(): ?Household
    {
        $household = $this->route('household');

        if ($household instanceof Household) {
            return $household;
        }

        return is_string($household)
            ? Household::query()->whereKey($household)->firstOrFail()
            : null;
    }
}
