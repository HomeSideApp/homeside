<?php

namespace App\Http\Requests;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class StoreEconomicImportUploadRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may upload an import in the requested scope.
     *
     * @return bool True when the request targets the private economy or a household the user belongs to.
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
     * Define validation rules for uploading and creating an economic import.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,application/pdf'],
            'sections' => ['required', 'array:place,date,items,taxes'],
            'sections.place' => ['required', 'boolean'],
            'sections.date' => ['required', 'boolean'],
            'sections.items' => ['required', 'boolean'],
            'sections.taxes' => ['required', 'boolean'],
            'image_processing' => ['nullable', 'array'],
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
