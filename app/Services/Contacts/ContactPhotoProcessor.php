<?php

namespace App\Services\Contacts;

use App\Models\ContactRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

final class ContactPhotoProcessor
{
    public function remove(ContactRecord $record): void
    {
        $path = DB::table('contact_record_photos')->where('contact_record_id', $record->id)->value('storage_path');
        DB::table('contact_record_photos')->where('contact_record_id', $record->id)->delete();
        if ($record->contact->preferred_photo_record_id === $record->id) {
            $fallback = DB::table('contact_record_photos')
                ->join('contact_records', 'contact_records.id', '=', 'contact_record_photos.contact_record_id')
                ->where('contact_records.contact_id', $record->contact_id)
                ->whereNull('contact_records.remote_deleted_at')
                ->orderByRaw('CASE WHEN contact_records.contact_source_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('contact_records.source_order')
                ->orderBy('contact_records.created_at')->orderBy('contact_records.id')
                ->value('contact_records.id');
            $record->contact->update(['preferred_photo_record_id' => $fallback]);
        }
        if (is_string($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function store(ContactRecord $record, string $bytes): void
    {
        if (strlen($bytes) > 3 * 1024 * 1024 || strlen($bytes) < 16) {
            throw new RuntimeException('Contact photo size is invalid.');
        }

        try {
            $image = (new ImageManager(new Driver))->decode($bytes);
            $image->orient()->scaleDown(width: 512, height: 512);
            $encoded = (string) $image->encode(new WebpEncoder(quality: 80));
        } catch (\Throwable) {
            throw new RuntimeException('Contact photo is not a supported image.');
        }

        $path = 'contacts/'.$record->contact_id.'/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $encoded);
        $oldPath = DB::table('contact_record_photos')->where('contact_record_id', $record->id)->value('storage_path');
        DB::table('contact_record_photos')->updateOrInsert(
            ['contact_record_id' => $record->id],
            ['storage_path' => $path, 'mime_type' => 'image/webp', 'size' => strlen($encoded), 'width' => $image->width(), 'height' => $image->height(), 'checksum' => hash('sha256', $encoded), 'updated_at' => now(), 'created_at' => now()],
        );
        $preferred = $record->contact->preferred_photo_record_id;
        $currentIsLocal = $preferred !== null && DB::table('contact_records')
            ->where('id', $preferred)->whereNull('contact_source_id')->exists();
        if ($record->contact_source_id === null || ! $currentIsLocal) {
            $record->contact->update(['preferred_photo_record_id' => $record->id]);
        }
        if (is_string($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }
    }
}
