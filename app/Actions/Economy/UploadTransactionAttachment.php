<?php

namespace App\Actions\Economy;

use App\Models\EconomicTransaction;
use App\Models\EconomicTransactionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Stores an image attached to an economic transaction as supporting evidence.
 */
final class UploadTransactionAttachment
{
    public const MAX_ATTACHMENTS = 5;

    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var array<int, string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

    /**
     * Validate, store and register an attachment for a transaction.
     *
     * Duplicated uploads are deduplicated by SHA-256 inside the same transaction, so uploading
     * the same photo twice never creates a second gallery entry.
     *
     * @param  EconomicTransaction  $transaction  The transaction receiving the attachment.
     * @param  UploadedFile  $file  The image uploaded by the user.
     * @param  User  $user  The user that owns the attachment.
     * @return EconomicTransactionAttachment The existing duplicate or newly persisted attachment.
     */
    public function execute(EconomicTransaction $transaction, UploadedFile $file, User $user): EconomicTransactionAttachment
    {
        $this->assertAllowed($transaction, $file);

        $sha256 = hash_file('sha256', $file->getRealPath());

        $existing = EconomicTransactionAttachment::query()
            ->where('transaction_id', $transaction->id)
            ->where('sha256', $sha256)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $fileName = $sha256.'.'.Str::lower($file->getClientOriginalExtension() ?: 'bin');
        $path = 'economy/attachments/'.$transaction->id.'/'.$fileName;
        $file->storeAs('economy/attachments/'.$transaction->id, $fileName, 'local');

        return EconomicTransactionAttachment::create([
            'transaction_id' => $transaction->id,
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => $sha256,
            'sort_order' => (int) EconomicTransactionAttachment::query()
                ->where('transaction_id', $transaction->id)
                ->max('sort_order') + 1,
        ]);
    }

    /**
     * Validate that the uploaded image is acceptable for the transaction gallery.
     *
     * @param  EconomicTransaction  $transaction  The transaction receiving the attachment.
     * @param  UploadedFile  $file  The candidate upload.
     * @return void This method does not return a value.
     */
    private function assertAllowed(EconomicTransaction $transaction, UploadedFile $file): void
    {
        $current = EconomicTransactionAttachment::query()
            ->where('transaction_id', $transaction->id)
            ->count();

        if ($current >= self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages([
                'attachments' => __('app.validation.attachment_limit', ['max' => self::MAX_ATTACHMENTS]),
            ]);
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachments' => __('app.validation.attachment_type'),
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'attachments' => __('app.validation.attachment_too_large'),
            ]);
        }
    }

    /**
     * Build a display thumbnail for an attachment.
     *
     * The thumbnail is generated lazily by the serving controller, which keeps the upload path
     * free of extra I/O while still serving reasonably sized images.
     *
     * @param  string  $disk  The filesystem disk holding the original image.
     * @param  string  $absolutePath  The absolute path of the stored image.
     * @return string|null The encoded thumbnail contents, or null when generation fails.
     */
    public function thumbnail(string $disk, string $absolutePath): ?string
    {
        try {
            $manager = new ImageManager(new GdDriver);
            $image = $manager->decodePath(Storage::disk($disk)->path($absolutePath));
            $image->orient();

            if ($image->width() > 1200 || $image->height() > 1200) {
                $image->scaleDown(width: 1200, height: 1200);
            }

            return (string) $image->encode(new JpegEncoder(quality: 80));
        } catch (\Exception) {
            return null;
        }
    }
}
