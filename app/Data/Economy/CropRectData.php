<?php

namespace App\Data\Economy;

use Illuminate\Validation\ValidationException;

/**
 * Class CropRectData
 *
 * Immutable normalized crop rectangle. Coordinates are expressed as fractions between 0 and 1 of
 * the source image dimensions, so the client never needs to know the real pixel size and the same
 * payload works for every resolution.
 */
final readonly class CropRectData
{
    /**
     * Create a normalized crop rectangle.
     *
     * @param  float  $x  The left offset as a fraction of the image width.
     * @param  float  $y  The top offset as a fraction of the image height.
     * @param  float  $width  The crop width as a fraction of the image width.
     * @param  float  $height  The crop height as a fraction of the image height.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {}

    /**
     * Build a crop rectangle from a validated request payload.
     *
     * @param  array{x?: float|int|string, y?: float|int|string, width: float|int|string, height: float|int|string}  $data  The validated crop values keyed by coordinate name.
     * @return self The normalized immutable crop rectangle.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            x: (float) ($data['x'] ?? 0),
            y: (float) ($data['y'] ?? 0),
            width: (float) $data['width'],
            height: (float) $data['height'],
        );
    }

    /**
     * Validate that the rectangle stays inside the normalized image bounds.
     *
     * @return void This method does not return a value.
     *
     * @throws ValidationException When a coordinate falls outside the 0 to 1 range or the
     *                             rectangle would extend past the image edges.
     */
    public function assertValid(): void
    {
        $values = [$this->x, $this->y, $this->width, $this->height];

        foreach ($values as $value) {
            if ($value < 0 || $value > 1) {
                throw ValidationException::withMessages([
                    'image_processing' => __('app.validation.invalid_processing'),
                ]);
            }
        }

        if ($this->width <= 0 || $this->height <= 0
            || $this->x + $this->width > 1.0001
            || $this->y + $this->height > 1.0001) {
            throw ValidationException::withMessages([
                'image_processing' => __('app.validation.invalid_processing'),
            ]);
        }
    }

    /**
     * Export the rectangle as a persistable array.
     *
     * @return array<string, float> The rectangle coordinates keyed by name.
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
