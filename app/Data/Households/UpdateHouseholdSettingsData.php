<?php

namespace App\Data\Households;

use Illuminate\Http\UploadedFile;

final readonly class UpdateHouseholdSettingsData
{
    /**
     * @param  array<string, bool>|null  $modules
     * @param  array<int, string>|null  $tags
     * @param  string|null  $defaultSplitType  The default expense split type for the economy module (SplitType value).
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $color = null,
        public ?UploadedFile $image = null,
        public bool $removeImage = false,
        public ?array $modules = null,
        public ?array $tags = null,
        public ?string $defaultSplitType = null,
    ) {}
}
