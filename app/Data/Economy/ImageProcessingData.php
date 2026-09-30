<?php

namespace App\Data\Economy;

use Illuminate\Validation\ValidationException;

/**
 * Class ImageProcessingData
 *
 * Immutable description of the adjustments applied to a receipt image before the AI analysis.
 * The original file is never modified: these values only drive the generation of the optimized
 * version that is sent to the provider.
 */
final readonly class ImageProcessingData
{
    /**
     * Create immutable image processing instructions.
     *
     * @param  CropRectData|null  $crop  The optional normalized crop rectangle.
     * @param  int  $rotate  The clockwise rotation in degrees, restricted to multiples of 90.
     * @param  int  $brightness  The brightness adjustment between -100 and 100.
     * @param  int  $contrast  The contrast adjustment between -100 and 100.
     * @param  bool  $greyscale  Whether the image should be converted to greyscale.
     * @param  bool  $sharpen  Whether a sharpening pass should be applied.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public ?CropRectData $crop = null,
        public int $rotate = 0,
        public int $brightness = 0,
        public int $contrast = 0,
        public bool $greyscale = false,
        public bool $sharpen = false,
    ) {}

    /**
     * Build processing instructions from a validated request payload.
     *
     * @param  array<string, mixed>  $data  The validated `processing` values keyed by option name.
     * @return self The normalized immutable processing instructions.
     */
    public static function fromArray(array $data): self
    {
        $crop = $data['crop'] ?? null;

        return new self(
            crop: is_array($crop) && isset($crop['width'], $crop['height'])
                ? CropRectData::fromArray($crop)
                : null,
            rotate: (int) ($data['rotate'] ?? 0),
            brightness: (int) ($data['brightness'] ?? 0),
            contrast: (int) ($data['contrast'] ?? 0),
            greyscale: filter_var($data['greyscale'] ?? false, FILTER_VALIDATE_BOOL),
            sharpen: filter_var($data['sharpen'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * Determine whether the instructions would change the image at all.
     *
     * @return bool True when no adjustment was requested, so the original pipeline can be used.
     */
    public function isNoop(): bool
    {
        return $this->crop === null
            && $this->rotate === 0
            && $this->brightness === 0
            && $this->contrast === 0
            && ! $this->greyscale
            && ! $this->sharpen;
    }

    /**
     * Validate the numeric ranges of the requested adjustments.
     *
     * @return void This method does not return a value.
     *
     * @throws ValidationException When a rotation is not a multiple of 90 degrees or an adjustment
     *                             falls outside its allowed range.
     */
    public function assertValid(): void
    {
        $this->crop?->assertValid();

        if ($this->rotate % 90 !== 0) {
            throw ValidationException::withMessages([
                'image_processing' => __('app.validation.invalid_processing'),
            ]);
        }

        foreach ([$this->brightness, $this->contrast] as $value) {
            if ($value < -100 || $value > 100) {
                throw ValidationException::withMessages([
                    'image_processing' => __('app.validation.invalid_processing'),
                ]);
            }
        }
    }

    /**
     * Export the instructions as a persistable array.
     *
     * @return array<string, mixed> The applied adjustments keyed by option name.
     */
    public function toArray(): array
    {
        return [
            'crop' => $this->crop?->toArray(),
            'rotate' => $this->rotate,
            'brightness' => $this->brightness,
            'contrast' => $this->contrast,
            'greyscale' => $this->greyscale,
            'sharpen' => $this->sharpen,
        ];
    }
}
