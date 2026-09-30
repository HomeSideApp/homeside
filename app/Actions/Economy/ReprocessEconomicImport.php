<?php

namespace App\Actions\Economy;

use App\Data\Economy\ImageProcessingData;
use App\Enums\EconomicImportStatus;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicImport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Regenerates the analysed image of an import with new adjustments and restarts the analysis.
 */
final class ReprocessEconomicImport
{
    /**
     * Rebuild the AI version of the import document and queue a new analysis.
     *
     * The previous AI version is removed so the storage never accumulates orphaned variants, and
     * the import returns to the pending state so the review screen resumes polling automatically.
     *
     * @param  EconomicImport  $import  The import to reprocess.
     * @param  ImageProcessingData  $processing  The adjustments requested by the user.
     * @return EconomicImport The refreshed import waiting for the new analysis.
     */
    public function execute(EconomicImport $import, ImageProcessingData $processing): EconomicImport
    {
        $processing->assertValid();

        $document = $import->document;

        if ($document === null) {
            throw ValidationException::withMessages([
                'image_processing' => __('app.validation.import_without_document'),
            ]);
        }

        if (! str_starts_with((string) $document->mime_type, 'image/')) {
            throw ValidationException::withMessages([
                'image_processing' => __('app.validation.processing_requires_image'),
            ]);
        }

        $storage = Storage::disk($document->disk);

        if (! $storage->exists($document->path)) {
            throw ValidationException::withMessages([
                'image_processing' => __('app.validation.import_document_missing'),
            ]);
        }

        $manager = new ImageManager(new GdDriver);
        $image = $manager->decodePath($storage->path($document->path));
        $image->orient();

        if ($processing->crop !== null) {
            $crop = $processing->crop;
            $width = max(1, (int) round($image->width() * $crop->width));
            $height = max(1, (int) round($image->height() * $crop->height));
            $x = max(0, (int) round($image->width() * $crop->x));
            $y = max(0, (int) round($image->height() * $crop->y));

            $image->crop(
                min($width, $image->width() - $x),
                min($height, $image->height() - $y),
                $x,
                $y,
            );
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

        if ($image->width() > 2048 || $image->height() > 2048) {
            $image->scaleDown(width: 2048, height: 2048);
        }

        $newPath = 'economy/documents/'.($import->household_id ?? 'user-'.$import->created_by).'/ai_'.uniqid().'.jpg';

        if ($document->ai_path !== null && $document->ai_path !== $document->path
            && $storage->exists($document->ai_path)) {
            $storage->delete($document->ai_path);
        }

        $storage->put($newPath, (string) $image->encode(new JpegEncoder(quality: 85)));

        $document->update([
            'ai_path' => $newPath,
            'processing_meta' => $processing->toArray(),
        ]);

        $import->update([
            'status' => EconomicImportStatus::Pending,
            'extracted_payload' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        AnalyzeEconomicDocumentJob::dispatch($import->id);

        return $import->refresh();
    }
}
