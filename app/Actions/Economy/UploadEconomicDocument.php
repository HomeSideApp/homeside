<?php

namespace App\Actions\Economy;

use App\Data\Economy\ImageProcessingData;
use App\Models\EconomicDocument;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Uploads an economic document, validating its type and size, deduplicating
 * by SHA-256 and generating an AI-optimised image version.
 */
final class UploadEconomicDocument
{
    /**
     * Validate, deduplicate, store, and register an uploaded economic document.
     *
     * @param  UploadedFile  $file  The document uploaded by the user.
     * @param  Household|null  $household  The household scope, or null for a private document.
     * @param  User  $user  The user uploading and owning the document.
     * @param  ImageProcessingData|null  $processing  The optional adjustments applied to the AI version.
     * @return EconomicDocument The existing duplicate or newly persisted economic document.
     */
    public function execute(
        UploadedFile $file,
        ?Household $household,
        User $user,
        ?ImageProcessingData $processing = null,
    ): EconomicDocument {
        $processing?->assertValid();

        $scopeKey = $household?->id ?? 'user-'.$user->id;

        $realMime = $file->getMimeType();
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'application/pdf'];

        if (! in_array($realMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => __('app.validation.economic_document_type', ['mime' => $realMime]),
            ]);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => __('app.validation.economic_document_too_large'),
            ]);
        }

        $sha256 = hash_file('sha256', $file->getRealPath());

        $existing = EconomicDocument::where('household_id', $household?->id)
            ->where('sha256', $sha256)
            ->when($household === null, fn ($query) => $query->where('uploaded_by', $user->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $disk = 'local';
        $path = $scopeKey.'/'.$sha256.'.'.Str::lower($file->getClientOriginalExtension() ?: 'bin');
        $file->storeAs('economy/documents', $path, $disk);

        $aiPath = null;
        if (str_starts_with((string) $realMime, 'image/')) {
            $aiPath = $this->generateAiVersion($file, $scopeKey, $disk, $processing);
        }

        return EconomicDocument::create([
            'household_id' => $household?->id,
            'uploaded_by' => $user->id,
            'disk' => $disk,
            'path' => 'economy/documents/'.$path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $realMime,
            'size' => $file->getSize(),
            'sha256' => $sha256,
            'ai_path' => $aiPath,
            'processing_meta' => $processing !== null && ! $processing->isNoop()
                ? $processing->toArray()
                : null,
        ]);
    }

    /**
     * Generate an optimised image version for AI analysis.
     *
     * @param  UploadedFile  $file  The validated source image.
     * @param  string  $scopeKey  The household or private storage scope key.
     * @param  string  $disk  The Laravel filesystem disk used for storage.
     * @param  ImageProcessingData|null  $processing  The optional crop, rotation and tone adjustments.
     * @return string|null The stored optimized image path, or null when optimization is unavailable.
     */
    private function generateAiVersion(
        UploadedFile $file,
        string $scopeKey,
        string $disk,
        ?ImageProcessingData $processing = null,
    ): ?string {
        try {
            $manager = new ImageManager(new GdDriver);
            $image = $manager->decodePath($file->getRealPath());

            $image->orient();

            if ($processing !== null && ! $processing->isNoop()) {
                $image = $this->applyProcessing($image, $processing);
            }

            if ($image->width() > 2048 || $image->height() > 2048) {
                $image->scaleDown(width: 2048, height: 2048);
            }

            $aiFilename = 'ai_'.Str::uuid().'.jpg';
            $aiPath = 'economy/documents/'.$scopeKey.'/'.$aiFilename;

            Storage::disk($disk)->put($aiPath, (string) $image->encode(new JpegEncoder(quality: 85)));

            return $aiPath;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Apply the user requested adjustments to a decoded image.
     *
     * The transformations run in a fixed order (rotate, crop, tones, sharpening) so the result is
     * deterministic for a given payload, and coordinates are converted from normalized to pixel
     * space with clamping for images smaller than the requested rectangle.
     *
     * @param  ImageInterface  $image  The decoded source image.
     * @param  ImageProcessingData  $processing  The requested adjustments.
     * @return ImageInterface The processed image.
     */
    private function applyProcessing(
        ImageInterface $image,
        ImageProcessingData $processing,
    ): ImageInterface {
        if ($processing->rotate !== 0) {
            $image->rotate(360 - $processing->rotate);
        }

        if ($processing->crop !== null) {
            $crop = $processing->crop;
            $width = max(1, (int) round($image->width() * $crop->width));
            $height = max(1, (int) round($image->height() * $crop->height));
            $x = max(0, (int) round($image->width() * $crop->x));
            $y = max(0, (int) round($image->height() * $crop->y));

            $width = min($width, $image->width() - $x);
            $height = min($height, $image->height() - $y);

            $image->crop($width, $height, $x, $y);
        }

        if ($processing->brightness !== 0) {
            $image->brightness($processing->brightness);
        }

        if ($processing->contrast !== 0) {
            $image->contrast($processing->contrast);
        }

        if ($processing->greyscale) {
            $image->grayscale();
        }

        if ($processing->sharpen) {
            $image->sharpen(amount: 10);
        }

        return $image;
    }
}
