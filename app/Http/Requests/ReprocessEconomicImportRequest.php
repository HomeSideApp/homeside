<?php

namespace App\Http\Requests;

use App\Models\EconomicImport;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class ReprocessEconomicImportRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may reprocess the routed import.
     *
     * @return bool True when the import is reachable by the current user in its scope.
     */
    public function authorize(): bool
    {
        $import = $this->route('import');

        if (! $import instanceof EconomicImport) {
            return false;
        }

        return $this->user()?->can('retry', $import) ?? false;
    }

    /**
     * Define the validation rules for regenerating the analysed image.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        return [
            'image_processing' => ['required', 'array'],
            'image_processing.crop' => ['nullable', 'array:x,y,width,height'],
            'image_processing.crop.x' => ['required_with:image_processing.crop', 'numeric', 'between:0,1'],
            'image_processing.crop.y' => ['required_with:image_processing.crop', 'numeric', 'between:0,1'],
            'image_processing.crop.width' => ['required_with:image_processing.crop', 'numeric', 'gt:0', 'max:1'],
            'image_processing.crop.height' => ['required_with:image_processing.crop', 'numeric', 'gt:0', 'max:1'],
            'image_processing.rotate' => ['nullable', 'integer', 'in:0,90,180,270'],
            'image_processing.brightness' => ['nullable', 'integer', 'between:-100,100'],
            'image_processing.contrast' => ['nullable', 'integer', 'between:-100,100'],
            'image_processing.greyscale' => ['nullable', 'boolean'],
            'image_processing.sharpen' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Resolve the optional household route parameter before authorization completes.
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
